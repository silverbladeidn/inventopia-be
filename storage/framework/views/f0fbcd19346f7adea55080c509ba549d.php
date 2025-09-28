<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Item Request Baru</title>
</head>

<body style="margin:0; padding:20px; background:#f0f2f5; font-family:Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
        <tr>
            <td>
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" align="center"
                    style="background:#ffffff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); padding:24px;">
                    <tr>
                        <td>
                            <h1 style="display:block; width:100%; background-color:green; color:#fff; font-size:24px; margin:0
                                0 16px 0; padding:12px 16px; border-radius:8px 8px 0 0;">Item Request
                                Baru</h1>

                            <p style="font-size:16px; margin:8px 0;">
                                <strong style="color:black;">Request No:</strong> <?php echo e($itemRequest->request_number); ?>

                            </p>
                            <p style="font-size:16px; margin:8px 0;">
                                <strong style="color:black;">User:</strong> <?php echo e($itemRequest->user->name ?? '-'); ?>

                            </p>
                            <p style="font-size:16px; margin:8px 0;">
                                <strong style="color:black;">Catatan:</strong> <?php echo e($itemRequest->note ?? '-'); ?>

                            </p>

                            <h3
                                style="color:#2c7be5; border-bottom:2px solid #2c7be5; padding-bottom:4px; margin-top:24px;">
                                Detail Item
                            </h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                style="border-collapse:collapse; margin-top:10px; width:100%;">
                                <thead>
                                    <tr style="background:#2c7be5; color:#fff;">
                                        <th align="left" style="width:50%; padding:8px; border:1px solid #000000;">
                                            Produk
                                        </th>
                                        <th align="left" style="width:50%; padding:8px; border:1px solid #000000;">
                                            Qty
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__currentLoopData = $itemRequest->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td style="padding:8px; border:1px solid #000000;"><?php echo e($d->product->name ?? '-'); ?>

                                        </td>
                                        <td style="padding:8px; border:1px solid #000000;"><?php echo e($d->requested_quantity); ?>

                                        </td>
                                    </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>

                            <p style="font-size:16px; margin:8px 0; color:black;">Untuk melakukan approval, silakan klik
                                link berikut:
                            </p>
                            <a href="<?php echo e(url('/item-requests/' . $itemRequest->id)); ?>"
                                style="color:#2c7be5; text-decoration:none;">Lihat Item Request</a>
                            <p style="margin-top:24px; font-size:16px; color:black;">
                                Terima kasih,<br>
                                <strong><?php echo e($itemRequest->user->name ?? 'Unknown'); ?></strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html><?php /**PATH E:\Database\MySQL\htdocs\inventopia-backend\resources\views/emails/item_request_created.blade.php ENDPATH**/ ?>