

<?php $__env->startSection('title', 'Register'); ?>

<?php $__env->startSection('content'); ?>
<div style="display: flex; justify-content: center; align-items: center; min-height: 80vh;">
    <div class="card" style="max-width: 400px; width: 100%;">
        <h2 style="text-align: center; margin-bottom: 30px; color: #667eea;">Register</h2>
        
        <form method="POST" action="<?php echo e(route('register')); ?>">
            <?php echo csrf_field(); ?>
            
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo e(old('name')); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>

            <div class="form-group">
                <label for="role">Register as</label>
                <select id="role" name="role" required>
                    <option value="customer" <?php echo e(old('role') == 'customer' ? 'selected' : ''); ?>>Customer</option>
                    <option value="cleaner" <?php echo e(old('role') == 'cleaner' ? 'selected' : ''); ?>>Cleaner</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
        </form>

        <p style="text-align: center; margin-top: 20px; color: #666;">
            Already have an account? <a href="<?php echo e(route('login')); ?>" style="color: #667eea;">Login here</a>
        </p>
    </div>
</div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Documents\Finalexam\resources\views/auth/register.blade.php ENDPATH**/ ?>