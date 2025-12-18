

<?php $__env->startSection('title', 'Verify Email OTP'); ?>

<?php $__env->startSection('content'); ?>
<div style="display: flex; justify-content: center; align-items: center; min-height: 80vh;">
    <div class="card" style="max-width: 400px; width: 100%;">
        <h2 style="text-align: center; margin-bottom: 30px; color: #667eea;">Verify Your Email</h2>
        
        <p style="text-align: center; margin-bottom: 20px; color: #666;">
            We've sent a 6-digit OTP code to your email address. Please enter it below to verify your account.
        </p>

        <?php if(session('success')): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <?php echo e(session('success')); ?>

            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('otp.verify')); ?>">
            <?php echo csrf_field(); ?>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email', session('email'))); ?>" required readonly style="background: #f3f4f6;">
            </div>

            <div class="form-group">
                <label for="otp">Enter OTP Code</label>
                <input type="text" id="otp" name="otp" value="<?php echo e(old('otp')); ?>" required autofocus maxlength="6" pattern="[0-9]{6}" placeholder="000000" style="text-align: center; font-size: 24px; letter-spacing: 8px;">
                <small style="color: #666; display: block; margin-top: 5px;">Enter the 6-digit code sent to your email</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 15px;">Verify OTP</button>
        </form>

        <form method="POST" action="<?php echo e(route('otp.resend')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="email" value="<?php echo e(old('email', session('email'))); ?>">
            <button type="submit" class="btn" style="width: 100%; background: #e5e7eb; color: #333;">
                Resend OTP
            </button>
        </form>

        <p style="text-align: center; margin-top: 20px; color: #666;">
            <a href="<?php echo e(route('login')); ?>" style="color: #667eea;">Back to Login</a>
        </p>
    </div>
</div>

<script>
    // Auto-focus and format OTP input
    document.getElementById('otp').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 6) {
            this.form.submit();
        }
    });
</script>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Documents\Finalexam\resources\views/auth/verify-otp.blade.php ENDPATH**/ ?>