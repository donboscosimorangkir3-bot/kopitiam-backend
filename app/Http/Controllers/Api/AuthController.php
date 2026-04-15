<?php

namespace App\Http\Controllers\Api;

use App\Models\OtpCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\SendOtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
            'phone'    => 'required|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone'    => $request->phone,
            'password' => bcrypt($request->password),
            'email_verified_at' => null,
        ]);

        // Generate 6 Digit OTP
        $otp = random_int(100000, 999999);

        OtpCode::updateOrCreate(
            ['email' => $request->email],
            ['otp' => $otp, 'expires_at' => Carbon::now()->addMinutes(5)]
        );

        // Kirim Email
        Mail::to($request->email)->send(new SendOtpMail($otp));

        return response()->json([
            'message' => 'Kode OTP telah dikirim ke email Anda.',
            'email' => $request->email
        ], 200);
    }

    // 2. VERIFIKASI OTP
    public function verifyOtp(Request $request) {
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required|string'
    ]);

    $otpData = OtpCode::where('email', $request->email)
                      ->where('otp', $request->otp)
                      ->where('expires_at', '>', Carbon::now())
                      ->first();

    if (!$otpData) {
        return response()->json(['message' => 'Kode OTP salah atau sudah kadaluarsa'], 400);
    }

    $user = User::where('email', $request->email)->first();

    // --- CARA LEBIH PASTI (Force Assignment) ---
    $user->email_verified_at = now(); // Isi langsung ke objek
    $user->save(); // Simpan ke database
    // --------------------------------------------

    $otpData->delete();

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message'      => 'Verifikasi berhasil, selamat datang!',
        'data'         => $user, // Sekarang otomatis sudah berisi tanggal karena kita pakai $user->save()
        'access_token' => $token,
        'token_type'   => 'Bearer'
    ], 200);
}

    // 3. LOGIN (DENGAN PROTEKSI OTP)
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

        // --- PROTEKSI KEAMANAN: Cek apakah sudah verifikasi email ---
        if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Akun Anda belum diverifikasi. Silakan cek email untuk kode OTP.',
                'email' => $user->email,
                'is_verified' => false
            ], 403); // Status 403 = Forbidden
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

    // 1. Fungsi Kirim OTP Lupa Password
public function forgotPassword(Request $request) {
    $request->validate(['email' => 'required|email|exists:users,email']);

    // Generate 6 digit OTP
    $otp = random_int(100000, 999999);

    // Simpan ke tabel otp_codes (Gunakan model yang sudah kita buat sebelumnya)
    \App\Models\OtpCode::updateOrCreate(
        ['email' => $request->email],
        ['otp' => $otp, 'expires_at' => now()->addMinutes(5)]
    );

    // Kirim Email (Gunakan mailable yang sudah kita buat sebelumnya)
    \Illuminate\Support\Facades\Mail::to($request->email)->send(new \App\Mail\SendOtpMail($otp));

    return response()->json(['message' => 'Kode reset password telah dikirim ke email Anda.'], 200);
}

// 2. Fungsi Reset Password Baru
public function resetPassword(Request $request) {
    $request->validate([
        'email' => 'required|email|exists:users,email',
        'otp' => 'required|string',
        'password' => 'required|min:8|confirmed', // 'confirmed' artinya butuh field password_confirmation
    ]);

    // Cek OTP
    $otpData = \App\Models\OtpCode::where('email', $request->email)
                      ->where('otp', $request->otp)
                      ->where('expires_at', '>', now())
                      ->first();

    if (!$otpData) {
        return response()->json(['message' => 'Kode OTP salah atau kadaluarsa'], 400);
    }

    // Update Password User
    $user = \App\Models\User::where('email', $request->email)->first();
    $user->update(['password' => bcrypt($request->password)]);

    // Hapus OTP setelah sukses digunakan (Keamanan: OTP Sekali Pakai)
    $otpData->delete();

    return response()->json(['message' => 'Password berhasil diubah. Silakan login kembali.'], 200);
}
}
