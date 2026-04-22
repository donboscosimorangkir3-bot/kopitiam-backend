<?php

namespace App\Http\Controllers\Api;

// Hapus import OtpCode karena sudah digabung ke tabel User
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

        // Generate 6 Digit OTP
        $otp = random_int(100000, 999999);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone'    => $request->phone,
            'password' => bcrypt($request->password),
            'email_verified_at' => null,
            'otp' => $otp, // Simpan langsung ke tabel users
            'otp_expires_at' => Carbon::now()->addMinutes(5),
            'is_verified' => false
        ]);

        // Kirim Email
        Mail::to($request->email)->send(new SendOtpMail($otp));

        return response()->json([
            'message' => 'Registrasi berhasil. Kode OTP telah dikirim ke email Anda.',
            'email' => $request->email
        ], 200);
    }

    // 2. VERIFIKASI OTP
    public function verifyOtp(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string'
        ]);

        // Cari user yang email dan OTP-nya cocok serta belum expired
        $user = User::where('email', $request->email)
                      ->where('otp', $request->otp)
                      ->where('otp_expires_at', '>', Carbon::now())
                      ->first();

        if (!$user) {
            return response()->json(['message' => 'Kode OTP salah atau sudah kadaluarsa'], 400);
        }

        // Update status verifikasi di tabel users
        $user->email_verified_at = now();
        $user->is_verified = true;
        $user->otp = null; // Hapus OTP setelah digunakan
        $user->otp_expires_at = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Verifikasi berhasil, selamat datang!',
            'data'         => $user,
            'access_token' => $token,
            'token_type'   => 'Bearer'
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
        'otp' => $otp,
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

    // 7. FORGOT PASSWORD (Kirim OTP)
    public function forgotPassword(Request $request) {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = User::where('email', $request->email)->first();
        $otp = random_int(100000, 999999);

        // Update OTP di tabel users
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(5)
        ]);

        Mail::to($request->email)->send(new SendOtpMail($otp));

        return response()->json(['message' => 'Kode reset password telah dikirim ke email Anda.'], 200);
    }

    // 8. RESET PASSWORD BARU
    public function resetPassword(Request $request) {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string',
            'password' => 'required|min:8|confirmed',
        ]);

        // Cek OTP di tabel users
        $user = User::where('email', $request->email)
                          ->where('otp', $request->otp)
                          ->where('otp_expires_at', '>', now())
                          ->first();

        if (!$user) {
            return response()->json(['message' => 'Kode OTP salah atau kadaluarsa'], 400);
        }

        // Update Password dan hapus OTP
        $user->update([
            'password' => bcrypt($request->password),
            'otp' => null,
            'otp_expires_at' => null
        ]);

        return response()->json(['message' => 'Password berhasil diubah. Silakan login kembali.'], 200);
    }
}
