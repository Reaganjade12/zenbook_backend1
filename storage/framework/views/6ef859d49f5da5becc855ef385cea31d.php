

<?php $__env->startSection('title', 'Customer Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="color: #667eea;">My Bookings</h1>
        <a href="<?php echo e(route('customer.booking.create')); ?>" class="btn btn-primary">Create New Booking</a>
    </div>

    <div class="filter-buttons">
        <a href="<?php echo e(route('customer.dashboard')); ?>" class="filter-btn <?php echo e(!request('status') ? 'active' : ''); ?>">All</a>
        <a href="<?php echo e(route('customer.dashboard', ['status' => 'pending'])); ?>" class="filter-btn <?php echo e(request('status') == 'pending' ? 'active' : ''); ?>">Pending</a>
        <a href="<?php echo e(route('customer.dashboard', ['status' => 'approved'])); ?>" class="filter-btn <?php echo e(request('status') == 'approved' ? 'active' : ''); ?>">Approved</a>
        <a href="<?php echo e(route('customer.dashboard', ['status' => 'declined'])); ?>" class="filter-btn <?php echo e(request('status') == 'declined' ? 'active' : ''); ?>">Declined</a>
        <a href="<?php echo e(route('customer.dashboard', ['status' => 'in_progress'])); ?>" class="filter-btn <?php echo e(request('status') == 'in_progress' ? 'active' : ''); ?>">In Progress</a>
        <a href="<?php echo e(route('customer.dashboard', ['status' => 'completed'])); ?>" class="filter-btn <?php echo e(request('status') == 'completed' ? 'active' : ''); ?>">Completed</a>
    </div>

    <?php if($bookings->count() > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Service Type</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Cleaner</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($booking->booking_date->format('M d, Y')); ?></td>
                    <td><?php echo e(date('h:i A', strtotime($booking->booking_time))); ?></td>
                    <td><?php echo e($booking->service_type); ?></td>
                    <td><?php echo e(Str::limit($booking->address, 30)); ?></td>
                    <td>
                        <span class="badge badge-<?php echo e(str_replace('_', '-', $booking->status)); ?>">
                            <?php echo e(ucfirst(str_replace('_', ' ', $booking->status))); ?>

                        </span>
                    </td>
                    <td><?php echo e($booking->cleaner ? $booking->cleaner->name : 'Not assigned'); ?></td>
                    <td><?php echo e($booking->created_at->format('M d, Y')); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; padding: 40px; color: #666;">No bookings found. <a href="<?php echo e(route('customer.booking.create')); ?>" style="color: #667eea;">Create your first booking!</a></p>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Documents\Finalexam\resources\views/customer/dashboard.blade.php ENDPATH**/ ?>