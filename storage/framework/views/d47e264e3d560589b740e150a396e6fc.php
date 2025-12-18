

<?php $__env->startSection('title', 'Login'); ?>

<?php $__env->startSection('content'); ?>
<div style="display: flex; justify-content: center; align-items: center; min-height: 80vh;">
    <div class="card" style="max-width: 400px; width: 100%;">
        <h2 style="text-align: center; margin-bottom: 30px; color: #667eea;">Login</h2>
        
        <form method="POST" action="<?php echo e(route('login')); ?>">
            <?php echo csrf_field(); ?>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="remember" style="width: auto;">
                    <span>Remember me</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>

        <p style="text-align: center; margin-top: 15px;">
            <a href="<?php echo e(route('password.request')); ?>" style="color: #667eea; font-size: 14px;">Forgot your password?</a>
        </p>

        <p style="text-align: center; margin-top: 20px; color: #666;">
            Don't have an account? <a href="<?php echo e(route('register')); ?>" style="color: #667eea;">Register here</a>
        </p>
    </div>
</div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Documents\Finalexam\resources\views/auth/login.blade.php ENDPATH**/ ?>