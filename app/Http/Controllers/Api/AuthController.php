<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
// use App\Mail\SendOtpMail; // di-comment karena sekarang pakai Microservice
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http; // UNTUK PANGGIL MICROSERVICE
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AuthController extends Controller
{
    // 1. REGISTRASI
public function register(Request $request) {
    $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6',
        'phone' => 'required|string',
    ]);

    // Generate OTP
    $otp = random_int(100000, 999999);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'phone' => $request->phone,
        'password' => Hash::make($request->password),
        'email_verified_at' => null,
        'otp' => Hash::make($otp),
        'otp_expires_at' => Carbon::now()->addMinutes(5),
        'is_verified' => false
    ]);

    // LOGIKA MICROSERVICE (Panggil Node.js Port 8001)
    try {
        $response = Http::timeout(5)->asJson()->post('http://127.0.0.1:8001/api/send-otp', [
    'email' => $request->email,
    'otp'   => $otp,
    'type'  => 'register', // atau 'forgot'
]);

        // Cek apakah microservice mengembalikan respons sukses
        if (!$response->successful()) {
            // Microservice hidup tapi gagal kirim → rollback user, kembalikan error
            $user->delete();
            return response()->json([
                'message' => 'Gagal mengirim kode OTP. Server notifikasi mengalami gangguan. Silakan coba beberapa saat lagi.',
                'error_code' => 'OTP_SERVICE_FAILED'
            ], 503);
        }

    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        // Microservice benar-benar mati / tidak bisa dihubungi → rollback user
        $user->delete();
        return response()->json([
            'message' => 'Server notifikasi sedang tidak aktif. Registrasi dibatalkan. Silakan coba beberapa saat lagi.',
            'error_code' => 'OTP_SERVICE_DOWN'
        ], 503);

    } catch (\Exception $e) {
        // Error tidak terduga lainnya → rollback user
        $user->delete();
        return response()->json([
            'message' => 'Terjadi kesalahan tidak terduga saat mengirim OTP. Silakan coba lagi.',
            'error_code' => 'OTP_SERVICE_ERROR'
        ], 500);
    }

    return response()->json([
        'message' => 'Registrasi berhasil. Kode OTP telah dikirim melalui Microservice Notifikasi.',
        'email' => $request->email
    ], 200);
}

    // 2. VERIFIKASI OTP (Tetap sama sesuai kode Anda)
    public function verifyOtp(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // CEK OTP DENGAN HASH
        if (!Hash::check($request->otp, $user->otp)) {
            return response()->json(['message' => 'OTP salah'], 400);
        }

        // CEK EXPIRED
        if (now()->gt($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP sudah kadaluarsa'], 400);
        }

        // Verifikasi
        $user->update([
            'email_verified_at' => now(),
            'is_verified' => true,
            'otp' => null,
            'otp_expires_at' => null
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Verifikasi berhasil',
            'access_token' => $token
        ], 200);
    }

    // 3. LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau password salah'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Cek apakah sudah verifikasi email
        if (is_null($user->email_verified_at) && $user->is_verified == 0) {
    // Jika email_verified_at KOSONG DAN is_verified juga 0 (False)
    // Berarti dia benar-benar belum verifikasi (Customer baru)

    $otp = random_int(100000, 999999);
    $user->update([
    'otp' => Hash::make($otp),
    'otp_expires_at' => now()->addMinutes(5)
]);
    Mail::to($user->email)->send(new SendOtpMail($otp));

    return response()->json([
        'message' => 'Akun Anda belum diverifikasi. Silakan cek email.',
        'email' => $user->email,
        'is_verified' => false
    ], 403);
}

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Login berhasil',
            'data'         => $user,
            'access_token' => $token,
            'token_type'   => 'Bearer'
        ], 200);
    }

    // 4. LOGOUT
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil'
        ]);
    }

    // 5. UPDATE PROFIL
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => 'nullable|string|max:255',
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data'    => $user
        ]);
    }

    // 6. UBAH PASSWORD
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed'
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password lama salah.'], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    // 7. FORGOT PASSWORD
public function forgotPassword(Request $request) {
    $request->validate(['email' => 'required|email|exists:users,email']);

    $user = User::where('email', $request->email)->first();
    $otp = random_int(100000, 999999);

    // Simpan OTP dulu ke DB (sementara)
    $previousOtp = $user->otp;
    $previousOtpExpiry = $user->otp_expires_at;

    $user->update([
        'otp' => Hash::make($otp),
        'otp_expires_at' => now()->addMinutes(5)
    ]);

    // LOGIKA MICROSERVICE
    try {
        $response = Http::timeout(5)->asJson()->post('http://127.0.0.1:8001/api/send-otp', [
    'email' => $request->email,
    'otp'   => $otp,
    'type'  => 'forgot',
]);

        if (!$response->successful()) {
            // Rollback OTP ke nilai sebelumnya
            $user->update([
                'otp' => $previousOtp,
                'otp_expires_at' => $previousOtpExpiry
            ]);
            return response()->json([
                'message' => 'Gagal mengirim kode OTP. Server notifikasi mengalami gangguan. Silakan coba beberapa saat lagi.',
                'error_code' => 'OTP_SERVICE_FAILED'
            ], 503);
        }

    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        // Rollback OTP ke nilai sebelumnya
        $user->update([
            'otp' => $previousOtp,
            'otp_expires_at' => $previousOtpExpiry
        ]);
        return response()->json([
            'message' => 'Server notifikasi sedang tidak aktif. Permintaan reset password dibatalkan. Silakan coba beberapa saat lagi.',
            'error_code' => 'OTP_SERVICE_DOWN'
        ], 503);

    } catch (\Exception $e) {
        $user->update([
            'otp' => $previousOtp,
            'otp_expires_at' => $previousOtpExpiry
        ]);
        return response()->json([
            'message' => 'Terjadi kesalahan tidak terduga saat mengirim OTP. Silakan coba lagi.',
            'error_code' => 'OTP_SERVICE_ERROR'
        ], 500);
    }

    return response()->json([
        'message' => 'Kode reset password telah dikirim ke email Anda melalui Microservice Notifikasi.',
        'email' => $request->email
    ], 200);
}

    // 8. RESET PASSWORD BARU
    public function resetPassword(Request $request) {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string',
            'password' => 'required|min:8|confirmed',
        ]);

        // Cek OTP di tabel users
        $user = User::where('email', $request->email)->first();

if (!$user || !Hash::check($request->otp, $user->otp)) {
    return response()->json(['message' => 'OTP salah'], 400);
}

if (now()->gt($user->otp_expires_at)) {
    return response()->json(['message' => 'OTP kadaluarsa'], 400);
}

        // Update Password dan hapus OTP
        $user->update([
            'password' => Hash::make($request->password),
            'otp' => null,
            'otp_expires_at' => null
        ]);

        return response()->json(['message' => 'Password berhasil diubah. Silakan login kembali.'], 200);
    }
}
