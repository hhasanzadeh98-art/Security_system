<?php
require_once 'database.php';

class User
{
    private $id;
    private $username;
    private $role;
    private $full_name;
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    private function hydrate($row)
    {
        $this->id = $row->id;
        $this->username = $row->username;
        $this->role = $row->role;
        $this->full_name = $row->full_name;
    }

    public function login($username, $password)
    {
        $query = "SELECT * FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();

        if ($row) {
            $storedPassword = $row->password;

            if (password_verify($password, $storedPassword)) {
                $this->hydrate($row);
                return $this;
            }

            if ($password === $storedPassword) {
                $this->upgradePassword($row->id, $password);
                $this->hydrate($row);
                return $this;
            }
        }
        return false;
    }

    private function upgradePassword($userId, $plainPassword)
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':password' => $hashedPassword, ':id' => $userId]);
    }

    public function changePassword($userId, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':password' => $hashedPassword, ':id' => $userId]);
    }

    public static function getById($id)
    {
        $conn = Database::getConnection();
        $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            $user = new User();
            $user->hydrate($row);
            return $user;
        }
        return null;
    }

    public function getAllGuards()
    {
        $query = "SELECT * FROM users WHERE role='guard' ORDER BY full_name ASC";
        $stmt = $this->conn->query($query);
        $guards = [];
        while ($row = $stmt->fetch()) {
            $user = new User();
            $user->hydrate($row);
            $guards[] = $user;
        }
        return $guards;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getName()
    {
        return $this->full_name;
    }
    public function getRole()
    {
        return $this->role;
    }
}

class Shift
{
    private $id;
    private $startTime;
    private $endTime;
    private $user;
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    private function hydrate($row)
    {
        $this->id = $row->id;
        $this->startTime = $row->start_time;
        $this->endTime = $row->end_time;
        $this->user = User::getById($row->user_id);
    }

    public function startShift($user_id)
    {
        $query = "INSERT INTO shifts (user_id, start_time) VALUES (:user_id, NOW())";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':user_id' => $user_id]);
    }

    public function endShift($user_id)
    {
        $query = "UPDATE shifts SET end_time = NOW() WHERE user_id = :user_id AND end_time IS NULL ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':user_id' => $user_id]);
    }

    public function getActiveShift($user_id)
    {
        $query = "SELECT * FROM shifts WHERE user_id = :user_id AND end_time IS NULL ORDER BY start_time DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $row = $stmt->fetch();

        if ($row) {
            $shift = new Shift();
            $shift->hydrate($row);
            return $shift;
        }
        return null;
    }

    public function getAllShifts()
    {
        $query = "SELECT * FROM shifts ORDER BY start_time DESC";
        $stmt = $this->conn->query($query);
        $shifts = [];
        while ($row = $stmt->fetch()) {
            $shift = new Shift();
            $shift->hydrate($row);
            $shifts[] = $shift;
        }
        return $shifts;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }
    public function getEndTime()
    {
        return $this->endTime;
    }
    public function isActive()
    {
        return $this->endTime == NULL;
    }
    public function getUser()
    {
        return $this->user;
    }
}

class ShiftSetting
{
    private $conn;
    private $id;
    private $settingName;
    private $startHour;
    private $startMinute;
    private $endHour;
    private $endMinute;
    private $isActive;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    private function hydrate($row)
    {
        $this->id = $row->id;
        $this->settingName = $row->setting_name;
        $this->startHour = (int)$row->start_hour;
        $this->startMinute = (int)$row->start_minute;
        $this->endHour = (int)$row->end_hour;
        $this->endMinute = (int)$row->end_minute;
        $this->isActive = (bool)$row->is_active;
    }

    public function getActiveSetting()
    {
        $query = "SELECT * FROM shift_settings WHERE is_active = TRUE LIMIT 1";
        $stmt = $this->conn->query($query);
        $row = $stmt->fetch();

        if ($row) {
            $this->hydrate($row);
            return $this;
        }

        $this->startHour = 6;
        $this->startMinute = 0;
        $this->endHour = 6;
        $this->endMinute = 0;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getSettingName()
    {
        return $this->settingName;
    }
    public function getStartHour()
    {
        return $this->startHour;
    }
    public function getStartMinute()
    {
        return $this->startMinute;
    }
    public function getEndHour()
    {
        return $this->endHour;
    }
    public function getEndMinute()
    {
        return $this->endMinute;
    }
    public function getIsActive()
    {
        return $this->isActive;
    }

    public function getStartMinutes()
    {
        return ($this->startHour * 60) + $this->startMinute;
    }
    public function getEndMinutes()
    {
        return ($this->endHour * 60) + $this->endMinute;
    }

    public function getStartTimeFormatted()
    {
        return sprintf("%02d:%02d", $this->startHour, $this->startMinute);
    }
    public function getEndTimeFormatted()
    {
        return sprintf("%02d:%02d", $this->endHour, $this->endMinute);
    }
}

class GuardSchedule
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function getMonthSchedule($year, $month)
    {
        $query = "SELECT day, guard_id FROM guard_schedules WHERE year = :year AND month = :month";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':year' => $year, ':month' => $month]);

        $schedules = new stdClass();
        while ($row = $stmt->fetch()) {
            $schedules->{$row->day} = $row->guard_id;
        }
        return $schedules;
    }

    public function setSchedule($year, $month, $day, $guard_id)
    {
        $query = "INSERT INTO guard_schedules (year, month, day, guard_id) 
                  VALUES (:year, :month, :day, :guard_id) 
                  ON DUPLICATE KEY UPDATE guard_id = :guard_id2";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':year' => $year,
            ':month' => $month,
            ':day' => $day,
            ':guard_id' => $guard_id,
            ':guard_id2' => $guard_id
        ]);
    }

    public function deleteSchedule($year, $month, $day)
    {
        $query = "DELETE FROM guard_schedules WHERE year = :year AND month = :month AND day = :day";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':year' => $year, ':month' => $month, ':day' => $day]);
    }

    public function getGuardSchedule($guard_id, $year, $month)
    {
        $query = "SELECT day FROM guard_schedules WHERE guard_id = :guard_id AND year = :year AND month = :month ORDER BY day";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':guard_id' => $guard_id, ':year' => $year, ':month' => $month]);

        $days = [];
        while ($row = $stmt->fetch()) {
            $days[] = $row->day;
        }
        return $days;
    }
}

class JalaliDate
{
    public static $month_names;
    public static $weekdays;

    public static function init()
    {
        self::$month_names = new stdClass();
        self::$month_names->{1} = 'فروردین';
        self::$month_names->{2} = 'اردیبهشت';
        self::$month_names->{3} = 'خرداد';
        self::$month_names->{4} = 'تیر';
        self::$month_names->{5} = 'مرداد';
        self::$month_names->{6} = 'شهریور';
        self::$month_names->{7} = 'مهر';
        self::$month_names->{8} = 'آبان';
        self::$month_names->{9} = 'آذر';
        self::$month_names->{10} = 'دی';
        self::$month_names->{11} = 'بهمن';
        self::$month_names->{12} = 'اسفند';

        self::$weekdays = new stdClass();
        self::$weekdays->{0} = 'یکشنبه';
        self::$weekdays->{1} = 'دوشنبه';
        self::$weekdays->{2} = 'سه‌شنبه';
        self::$weekdays->{3} = 'چهارشنبه';
        self::$weekdays->{4} = 'پنج‌شنبه';
        self::$weekdays->{5} = 'جمعه';
        self::$weekdays->{6} = 'شنبه';
    }

    public static function gregorianToJalali($g_y, $g_m, $g_d)
    {
        $gy = (int)$g_y;
        $gm = (int)$g_m;
        $gd = (int)$g_d;

        $g_days_in_month = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        if ((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0)) {
            $g_days_in_month[2] = 29;
        }

        $g_day_no = $gd;
        for ($i = 1; $i < $gm; $i++) {
            $g_day_no += $g_days_in_month[$i];
        }

        $gy2 = $gy - 1;
        $days_from_1_ad = $gy2 * 365 + floor($gy2 / 4) - floor($gy2 / 100) + floor($gy2 / 400);
        $days_from_1_ad += $g_day_no;

        $days_622_year = 621 * 365 + floor(621 / 4) - floor(621 / 100) + floor(621 / 400);
        $days_622_year += 79;

        $jalali_epoch = $days_622_year;
        $jalali_days = $days_from_1_ad - $jalali_epoch;

        $jy = 1;
        while (true) {
            $year_days = self::isKabise($jy) ? 366 : 365;

            if ($jalali_days <= $year_days) {
                break;
            }

            $jalali_days -= $year_days;
            $jy++;
        }

        $jm = 1;
        while ($jm <= 12) {
            $month_days = self::getMonthDays($jy, $jm);

            if ($jalali_days <= $month_days) {
                break;
            }

            $jalali_days -= $month_days;
            $jm++;
        }

        $jd = (int)$jalali_days;

        $result = new stdClass();
        $result->year = $jy;
        $result->month = $jm;
        $result->day = $jd;
        return $result;
    }

    public static function jalaliToGregorian($jy, $jm, $jd)
    {
        $jy = (int)$jy;
        $jm = (int)$jm;
        $jd = (int)$jd;

        $days = $jd;

        for ($m = 1; $m < $jm; $m++) {
            $days += self::getMonthDays($jy, $m);
        }

        for ($y = 1; $y < $jy; $y++) {
            $days += self::isKabise($y) ? 366 : 365;
        }

        $days_622_year = 621 * 365 + floor(621 / 4) - floor(621 / 100) + floor(621 / 400);
        $days_622_year += 79;

        $days += $days_622_year;

        $gy = 1;
        while (true) {
            $is_leap = ((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0));
            $year_days = $is_leap ? 366 : 365;

            if ($days <= $year_days) {
                break;
            }

            $days -= $year_days;
            $gy++;
        }

        $g_days_in_month = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0)) {
            $g_days_in_month[2] = 29;
        }

        $gm = 1;
        while ($gm <= 12) {
            if ($days <= $g_days_in_month[$gm]) {
                break;
            }
            $days -= $g_days_in_month[$gm];
            $gm++;
        }

        $gd = (int)$days;

        return [$gy, $gm, $gd];
    }

    public static function isKabise($year)
    {
        $year = (int)$year;
        $remainder = $year % 33;
        $kabise_reminders = [1, 5, 9, 13, 17, 22, 26, 30];
        return in_array($remainder, $kabise_reminders);
    }

    public static function getMonthDays($year, $month)
    {
        $month = (int)$month;
        $year = (int)$year;

        if ($month >= 1 && $month <= 6) return 31;
        if ($month >= 7 && $month <= 11) return 30;
        if ($month == 12) return self::isKabise($year) ? 30 : 29;
        return 30;
    }

    public static function getWeekday($year, $month, $day)
    {
        $gregorian = self::jalaliToGregorian($year, $month, $day);
        $gy = $gregorian[0];
        $gm = $gregorian[1];
        $gd = $gregorian[2];

        $date_string = sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
        $timestamp = strtotime($date_string);

        if ($timestamp === false) {
            $timestamp = mktime(0, 0, 0, $gm, $gd, $gy);
        }

        $w = date('w', $timestamp);
        return self::$weekdays->{$w};
    }

    public static function getDayOfYear($year, $month, $day)
    {
        $dayOfYear = $day;
        for ($i = 1; $i < $month; $i++) {
            $dayOfYear += self::getMonthDays($year, $i);
        }
        return $dayOfYear;
    }

    public static function getToday()
    {
        $gy = (int)date('Y');
        $gm = (int)date('m');
        $gd = (int)date('d');

        return self::gregorianToJalali($gy, $gm, $gd);
    }

    public static function format($year, $month, $day, $format = 'Y/m/d')
    {
        $y = (int)$year;
        $m = (int)$month;
        $d = (int)$day;

        $formats = new stdClass();
        $formats->{'Y'} = $y;
        $formats->{'y'} = substr($y, -2);
        $formats->{'m'} = sprintf('%02d', $m);
        $formats->{'n'} = $m;
        $formats->{'d'} = sprintf('%02d', $d);
        $formats->{'j'} = $d;
        $formats->{'F'} = self::$month_names->{$m};
        $formats->{'M'} = substr(self::$month_names->{$m}, 0, 3);
        $formats->{'l'} = self::getWeekday($y, $m, $d);
        $formats->{'D'} = substr(self::getWeekday($y, $m, $d), 0, 2);

        $result = $format;
        foreach (get_object_vars($formats) as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        return $result;
    }
}

JalaliDate::init();

class TimeFormatter
{
    public static function minutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf("%02d:%02d", $hours, $mins);
    }

    public static function formatDuration($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours > 0 && $mins > 0) {
            return $hours . " ساعت و " . $mins . " دقیقه";
        } elseif ($hours > 0) {
            return $hours . " ساعت";
        } else {
            return $mins . " دقیقه";
        }
    }
}

class DateTimeConverter
{
    public static function convertToJalaliDateTime($datetime)
    {
        $timestamp = strtotime($datetime);
        $g_y = date('Y', $timestamp);
        $g_m = date('m', $timestamp);
        $g_d = date('d', $timestamp);
        $time = date('H:i:s', $timestamp);

        $jalali = JalaliDate::gregorianToJalali($g_y, $g_m, $g_d);

        return $jalali->day . ' ' . JalaliDate::$month_names->{$jalali->month} . ' ' . $jalali->year . ' - ' . $time;
    }
}
