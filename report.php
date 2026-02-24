<?php
session_start();
require_once 'classes.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guard') {
    header("Location: auth.php");
    exit();
}

$userModel = new User();
$currentUser = $userModel->getById($_SESSION['user_id']);

if (!$currentUser) {
    die("خطا: اطلاعات کاربر یافت نشد");
}

// امروز
$todayJalali = JalaliDate::getToday();
$todayReportDate = date('Y-m-d');

// مدل گزارش (این خط حیاتی است!)
$reportModel = new GuardShiftReport(Database::getConnection());

// گرفتن گزارش امروز
$existingReport = $reportModel->getByDateAndGuard($todayReportDate, $currentUser->getId());

// بقیه کد فایل...
// ... قسمت بالای فایل همان قبلی ...

$today = JalaliDate::getToday();
$todayReportDate = date('Y-m-d');

// آیا گزارش امروز ثبت شده؟
$existingReport = $reportModel->getByDateAndGuard($todayReportDate, $currentUser->getId());

// محاسبه نگهبان بعدی طبق شیفت‌بندی
$scheduleModel = new GuardSchedule();
$tomorrowJalali = JalaliDate::gregorianToJalali(date('Y', strtotime('+1 day')), date('m', strtotime('+1 day')), date('d', strtotime('+1 day')));
$nextGuardId = $scheduleModel->getGuardForDate($tomorrowJalali->year, $tomorrowJalali->month, $tomorrowJalali->day);
$nextGuardName = $nextGuardId ? $scheduleModel->getGuardName($nextGuardId) : 'نامشخص (شیفت بعدی تنظیم نشده)';

// اگر گزارش وجود داشت → نگهبان قبلی را از گزارش بخوانیم
$prevGuardName = $existingReport && $existingReport->previous_guard_id 
    ? $scheduleModel->getGuardName($existingReport->previous_guard_id)
    : 'نامشخص';

?>

<!-- دکمه باز کردن مودال -->
<div style="text-align:center; margin:40px 0;">
    <button id="openReportModal" class="btn-primary" style="font-size:1.3em; padding:15px 40px;">
        <?php echo $existingReport ? 'ویرایش گزارش امروز' : 'ثبت گزارش شیفت امروز'; ?>
    </button>
</div>

<!-- مودال -->
<div id="reportModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:white; width:90%; max-width:780px; max-height:90vh; overflow-y:auto; border-radius:12px; padding:25px; position:relative;">
        <button id="closeModal" style="position:absolute; top:15px; left:15px; font-size:1.6em; background:none; border:none; cursor:pointer;">×</button>

        <h2 style="text-align:center; margin-bottom:25px;">
            <?php echo $existingReport ? 'ویرایش گزارش شیفت امروز' : 'ثبت گزارش شیفت امروز'; ?>
        </h2>

        <p style="text-align:center; color:#555; margin-bottom:20px;">
            <?php echo JalaliDate::format($today->year, $today->month, $today->day, 'l، j F Y'); ?>
        </p>

        <form method="post" id="reportForm">
            <input type="hidden" name="report_date" value="<?php echo $todayReportDate; ?>">

            <!-- نوع شیفت -->
            <label>نوع شیفت:</label>
            <select name="shift_type" style="width:100%; padding:10px; margin-bottom:15px;">
                <option value="24h" <?php echo ($existingReport->shift_type??'')==='24h'?'selected':''; ?>>۲۴ ساعته</option>
                <option value="morning" <?php echo ($existingReport->shift_type??'')==='morning'?'selected':''; ?>>صبح تا عصر</option>
                <option value="evening" <?php echo ($existingReport->shift_type??'')==='evening'?'selected':''; ?>>عصر تا صبح</option>
            </select>

            <!-- تیک پایان شیفت و تحویل -->
            <div style="margin:20px 0; padding:15px; background:#f8f9fa; border-radius:8px;">
                <label style="font-weight:bold; display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" id="isHandover" name="is_handover" <?php echo $existingReport && $existingReport->handover_time ? 'checked' : ''; ?>>
                    شیفت به پایان رسیده و تحویل داده شده است
                </label>

                <div id="handoverFields" style="margin-top:15px; <?php echo $existingReport && $existingReport->handover_time ? '' : 'display:none;'; ?>">
                    <label>ساعت تحویل پست:</label>
                    <input type="time" name="handover_time" value="<?php echo htmlspecialchars($existingReport->handover_time ?? ''); ?>" style="width:100%; padding:10px;">

                    <label style="margin-top:15px;">تحویل به (نگهبان بعدی طبق شیفت‌بندی):</label>
                    <input type="text" readonly value="<?php echo htmlspecialchars($nextGuardName); ?>" style="width:100%; padding:10px; background:#eee; border:1px solid #ccc; cursor:not-allowed;">
                    <input type="hidden" name="previous_guard_id" value="<?php echo $nextGuardId ?: ''; ?>">
                </div>
            </div>

            <!-- بقیه فیلدها (چک‌باکس‌ها، متن‌ها و ...) همان قبلی -->

            <!-- دکمه ارسال -->
            <div style="text-align:center; margin-top:30px;">
                <button type="submit" class="btn-primary" style="padding:12px 50px; font-size:1.1em;">
                    <?php echo $existingReport ? 'به‌روزرسانی گزارش' : 'ثبت گزارش'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// باز و بسته کردن مودال
document.getElementById('openReportModal').onclick = function() {
    document.getElementById('reportModal').style.display = 'flex';
};

document.getElementById('closeModal').onclick = function() {
    document.getElementById('reportModal').style.display = 'none';
};

// نمایش/مخفی کردن فیلدهای تحویل با تیک
document.getElementById('isHandover').onchange = function() {
    document.getElementById('handoverFields').style.display = this.checked ? 'block' : 'none';
};
</script>

<style>
.modal { transition: all 0.3s; }
.modal > div { transform: scale(0.9); transition: transform 0.3s; }
.modal.show > div { transform: scale(1); }
</style>