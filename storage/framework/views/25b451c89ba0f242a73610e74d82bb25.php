

<?php $__env->startSection('title', 'Create Booking'); ?>

<?php $__env->startSection('content'); ?>
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h1 style="color: #667eea; margin-bottom: 30px;">Create New Booking</h1>
    
    <form method="POST" action="<?php echo e(route('customer.booking.store')); ?>">
        <?php echo csrf_field(); ?>
        
        <div class="form-group">
            <label for="booking_date">Booking Date</label>
            <input type="date" id="booking_date" name="booking_date" value="<?php echo e(old('booking_date')); ?>" required min="<?php echo e(date('Y-m-d')); ?>">
        </div>

        <div class="form-group">
            <label for="booking_time">Booking Time</label>
            <input type="time" id="booking_time" name="booking_time" value="<?php echo e(old('booking_time')); ?>" required>
        </div>

        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="3" required><?php echo e(old('address')); ?></textarea>
        </div>

        <div class="form-group">
            <label for="service_type">Service Type</label>
            <select id="service_type" name="service_type" required>
                <option value="">Select service type</option>
                <option value="Deep Cleaning" <?php echo e(old('service_type') == 'Deep Cleaning' ? 'selected' : ''); ?>>Deep Cleaning</option>
                <option value="Regular Cleaning" <?php echo e(old('service_type') == 'Regular Cleaning' ? 'selected' : ''); ?>>Regular Cleaning</option>
                <option value="Move-in/Move-out Cleaning" <?php echo e(old('service_type') == 'Move-in/Move-out Cleaning' ? 'selected' : ''); ?>>Move-in/Move-out Cleaning</option>
                <option value="Office Cleaning" <?php echo e(old('service_type') == 'Office Cleaning' ? 'selected' : ''); ?>>Office Cleaning</option>
                <option value="Window Cleaning" <?php echo e(old('service_type') == 'Window Cleaning' ? 'selected' : ''); ?>>Window Cleaning</option>
                <option value="Carpet Cleaning" <?php echo e(old('service_type') == 'Carpet Cleaning' ? 'selected' : ''); ?>>Carpet Cleaning</option>
            </select>
        </div>

        <div class="form-group">
            <label for="notes">Additional Notes (Optional)</label>
            <textarea id="notes" name="notes" rows="4"><?php echo e(old('notes')); ?></textarea>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Create Booking</button>
            <a href="<?php echo e(route('customer.dashboard')); ?>" class="btn" style="background: #e5e7eb; color: #333;">Cancel</a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Documents\Finalexam\resources\views/customer/create-booking.blade.php ENDPATH**/ ?>