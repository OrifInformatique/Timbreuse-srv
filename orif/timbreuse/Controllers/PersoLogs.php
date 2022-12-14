<?php

namespace Timbreuse\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Timbreuse\Models\BadgesModel;
use Timbreuse\Models\LogsModel;
use Timbreuse\Models\UsersModel;
use CodeIgniter\I18n\Time;
use Timbreuse\Models\AccessTimModel;
use Timbreuse\Models\LogsFakeLogsModel;
use Timbreuse\Models\FakeLogsModel;

class PersoLogs extends BaseController
{
    const RETURN_METHOD_NAME = 'perso_time';
    
    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        $this->access_level = config(
            '\User\Config\UserConfig'
        )->access_lvl_registered;
        parent::initController($request, $response, $logger);
        $this->session = \Config\Services::session();
    }

    /**
     * @deprecated
     * is move in adminLogs
     */
    private function perso_logs_list($userId, $day = null, $period = null)
    {
        trigger_error('Deprecated function called.', E_USER_DEPRECATED);
        if (($day === null) or ($day == 'all')) {
            return redirect()->to(
                $userId . '/' . Time::today()->toDateString() . '/all'
            );
        }
        if ($period === null) {
            return redirect()->to($day . '/day');
        }

        $usersModel = model(UsersModel::class);
        $user = $usersModel->get_users($userId);

        $data['title'] = "Welcome";

        # Display a test of the generic "items_list" view (defined in common
        # module)
        $data['columns'] = [
            'date' => 'Date',
            'id_badge' => 'Numéro du badge',
            'inside' => 'Entrée'
        ];
        $day = Time::parse($day);
        $data['period'] = $period;
        $logsModel = model(LogsModel::class);
        $data['items'] = $logsModel->get_filtered_logs($userId, $day, $period);
        $sumTime = [
            'date' => 'Total temps',
            'id_badge' => $this->get_hours_by_seconds(
                $this->get_time_array($data['items'])
            ),
            'inside' => ''
        ];
        array_push($data['items'], $sumTime);


        $data['list_title'] = $this->create_title($user, $day, $period);
        $data['buttons'] = $this->create_buttons($period);
        if ($period != 'all') {
            $data['buttons'] = array_merge(
                $this->create_time_links($day, $period),
                $data['buttons']
            );
            $data['date'] = $day->toDateString();
        }
        $this->display_view(
            [
                'Timbreuse\Views\period_menu',
                'Timbreuse\Views\date', 'Common\Views\items_list'
            ],
            $data
        );
    }

    protected function get_last_monday(Time $day)
    {
        return $day->subDays($day->dayOfWeek - 1);
    }

    protected function get_month_week_array($userId, Time $date): array
    {
        $weeks = array();
        $firstDay = Time::create($date->year, $date->month, 1);
        $monday = $this->get_last_monday($firstDay);

        do {
            array_push($weeks, $this->get_day_week_array($userId, $monday));
            $monday = $monday->addDays(7);
        } while ($monday->month == $date->month);

        return $weeks;
    }

    protected function get_workdays_text($date): string
    {
        $monday = $this->get_last_monday($date);
        $friday = $monday->addDays(6);
        return sprintf(
            '%02d.%02d – %02d.%02d',
            $monday->day,
            $monday->month,
            $friday->day,
            $friday->month
        );
    }

    static function get_hours_by_seconds($seconds): string
    {
        $hours = floor($seconds / 3600);
        $seconds -= $hours * 3600;

        $minutes = floor($seconds / 60);
        $seconds -= $minutes * 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * calculate the time
     */
    protected function get_time_array($logs): int
    {
        $date_in = null;
        $seconds = array_reduce(
            $logs,
            function ($carry, $log) use (&$date_in) {
                if (boolval($log['inside'])) {
                    if (($date_in === null) or !($this->is_same_day(
                        Time::parse($date_in),
                        Time::parse($log['date'])
                    ))) {
                        $date_in = $log['date'];
                    }
                } elseif ($date_in !== null) {
                    if ($this->is_same_day(
                        Time::parse($date_in),
                        Time::parse($log['date'])
                    )) {
                        $carry += Time::parse($log['date'])
                            ->difference($date_in)->seconds;
                        $date_in = null;
                    }
                }
                return $carry;
            }
        );
        if ($seconds === null) {
            $seconds = 0;
        }
        return $seconds;
    }

    protected function get_day_week_array($userId, Time $date): array
    {
        $model = model(LogsModel::class);
        $fakeModel = model(LogsFakeLogsModel::class);
        $logs = $model->get_filtered_logs($userId, $date, 'week');
        $fakeLogs = $fakeModel->get_filtered_logs($userId, $date, 'week');
        $data['label_week'] = $this->get_workdays_text($date);
        $data['time'] = $this->get_hours_by_seconds(
            $this->get_time_array($logs)
        );
        $data['modifyTime'] = $this->get_hours_by_seconds(
            $this->get_time_array($fakeLogs)
        );
        $data['url'] = '../' . $date->toDateString() . '/week';
        return $data;
    }


    protected function time_list_day($userId, $day = null, $period = null)
    {
        $usersModel = model(UsersModel::class);
        $user = $usersModel->get_users($userId);

        $data['title'] = "Welcome";
        $data['columns'] = array();
        $data['columns'][0] = lang('tim_lang.hour');
        $data['columns'][1] = lang('tim_lang.enter/exit');

        $day = Time::parse($day);
        $data['period'] = $period;
        $data['items'] = $this->get_day_view_day_array($userId, $day, true);
        $sumTime = array();
        $sumTime['time'] = $this->get_time_day_by_period(
            $userId,
            $day,
            $period
        );
        $sumModifyTime['time'] = $this->get_time_day_by_period(
            $userId,
            $day,
            $period,
            true
        );
        $sumTime['date'] = ucfirst(lang('tim_lang.timeTotal'));
        $sumModifyTime['date'] = ucfirst(lang('tim_lang.modifyTime'));
        array_push($data['items'], $sumTime);
        array_push($data['items'], $sumModifyTime);

        $data['list_title'] = $this->create_title($user, $day, $period);
        $data['buttons'] = $this->create_buttons($period, true);
        if ($period != 'all') {
            $data['buttons'] = array_merge(
                $this->create_time_links($day, $period),
                $data['buttons']
            );
            $data['date'] = $day->toDateString();
        }
        $data['userId'] = $userId;
        $this->display_view(
            [
                'Timbreuse\Views\period_menu',
                'Timbreuse\Views\date', 'Timbreuse\Views\logs\day_time.php'
            ],
            $data
        );
    }

    protected function time_list_month($userId, $day = null, $period = null)
    {

        $usersModel = model(UsersModel::class);
        $user = $usersModel->get_users($userId);

        $data['title'] = "Welcome";
        $data['columns'] = array();
        $data['columns'][0] = lang('tim_lang.week');
        $data['columns'][1] = lang('tim_lang.time');
        $data['columns'][2] = lang('tim_lang.modifyTime');

        $day = Time::parse($day);
        $data['period'] = $period;
        $data['items'] = $this->get_month_week_array($userId, $day);

        $data['list_title'] = $this->create_title($user, $day, $period);
        $data['buttons'] = $this->create_buttons($period, true);
        if ($period != 'all') {
            $data['buttons'] = array_merge(
                $this->create_time_links($day, $period),
                $data['buttons']
            );
            $data['date'] = $day->toDateString();
        }
        $this->display_view(
            [
                'Timbreuse\Views\period_menu',
                'Timbreuse\Views\date', 'Timbreuse\Views\logs\month_time.php'
            ],
            $data
        );
    }

    /**
     * use for week view with time
     */
    protected function get_day_time_table(
        $userId,
        $date,
        $halfDay,
        $fakeLog = false
    ): array
    {
        if (!$fakeLog) {
            $model = model(LogsModel::class);
        } else {
            $model = model(LogsFakeLogsModel::class);
        }
        $logs = $model->get_logs_by_period($userId, $date, $halfDay);
        $data['time'] = $this->get_time_array($logs);
        $data['time'] = $this->get_hours_by_seconds($data['time']);
        if ($this->is_fake_log($logs)) {
            $data['time'] = $data['time'] . '✱';
        }
        $data['firstEntry'] = $this->get_string_time_for_day_time_table(
            $userId,
            $date,
            $halfDay,
            false,
            $model
        );
        $data['lastOuting'] = $this->get_string_time_for_day_time_table(
            $userId,
            $date,
            $halfDay,
            true,
            $model
        );
        return $data;
    }

    protected function get_string_time_for_day_time_table(
        int $userId,
        $date,
        $halfDay,
        bool $isLast,
        LogsModel $model
    ): string {
        try {
            $entry = $model->get_border_log_by_period(
                $userId,
                $date,
                $halfDay,
                $isLast
            );
            $entryStr = Time::parse($entry['date'])
                ->toTimeString();

            if (isset($entry['id_fake_log'])) {
                $entryStr .= '✱';
            }
        } catch (\Exception $e) {
            $entryStr = '';
        }
        return $entryStr;
    }

    protected function is_fake_log(array $logs) {
        foreach ($logs as $log) {
            if (isset($log['id_fake_log'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * use for week view with time
     */
    protected function get_upper_day_time_table(
        $userId,
        $date,
        $fakeLog = false
    ): array
    {
        $data['dayNb'] = $date->day;
        $data['url'] = '../' . $date->toDateString() . '/day';
        $data['morning'] = $this->get_day_time_table(
            $userId,
            $date,
            'morning',
            $fakeLog
        );
        $data['afternoon'] = $this->get_day_time_table(
            $userId,
            $date,
            'afternoon',
            $fakeLog
        );
        $data['time'] = $this->get_time_day_by_period(
            $userId,
            $date,
            'day',
            $fakeLog,
            $fakeLog,
        );
        return $data;
    }

    /**
     * use for week view with time
     */
    protected function get_week_time_table(
        $userId,
        $date,
        $fakeLog = false
    ): array
    {
        $monday = $this->get_last_monday($date);
        $weekdays = [
            'monday',
            'tuesday',
            'wednesday',
            'thurday',
            'friday',
        ];
        $data = array();
        foreach ($weekdays as $i => $weekday) {
            $data[$weekday] = $this->get_upper_day_time_table(
                $userId,
                $monday->addDays($i),
                $fakeLog
            );
        }
        return $data;
    }

    protected function time_list_week(
        $userId,
        $day = null,
        $period = null
    ): void
    {
        if (!(session()->has('isFakeLog'))) {
            session()->set('isFakeLog', true);
        }
        $data['isFakeLog'] = session()->get('isFakeLog');
        $usersModel = model(UsersModel::class);
        $user = $usersModel->get_users($userId);

        $data['title'] = "Welcome";
        $data['rows'] = [
            'morning' => lang('tim_lang.rowMorning'),
            'afternoon' => lang('tim_lang.rowAfternoon'),
            'total' => ucfirst(lang('tim_lang.timeTotal')),
        ];
        $data['rows2'] = [
            'time' => lang('tim_lang.time'),
            'firstEntry' => lang('tim_lang.firstEntry'),
            'lastOuting' => lang('tim_lang.lastOuting'),
        ];
        $day = Time::parse($day);
        $data['period'] = $period;
        $data['items'] = $this->get_week_time_table(
            $userId,
            $day,
            $data['isFakeLog']
        );
        $data['sumTime'] = $this->get_time_day_by_period(
            $userId,
            $day,
            $period,
            $data['isFakeLog'],
            $data['isFakeLog'],
        );

        $data['list_title'] = $this->create_title($user, $day, $period);
        $data['buttons'] = $this->create_buttons($period, true);

        array_push($data['buttons'], [
            'link' => '../../../../PersoLogs/turnSiteData',
            'label' => ucfirst(lang('tim_lang.siteData'))
        ]);

        if ($period != 'all') {
            $data['buttons'] = array_merge(
                $this->create_time_links($day, $period),
                $data['buttons']
            );
            $data['date'] = $day->toDateString();
        }
        $this->display_view(
            [
                'Timbreuse\Views\period_menu',
                'Timbreuse\Views\date', 'Timbreuse\Views\logs\week_time.php'
            ],
            $data
        );
    }

    public function turnSiteData()
    {
        session()->set('isFakeLog', !session()->get('isFakeLog'));
        return redirect()->back();
    }

    /**
     * @deprecated
     * is move in adminLogs
     */
    private function time_list($userId, $day = null, $period = null)
    {
        trigger_error('Deprecated function called.', E_USER_DEPRECATED);
        if (($day === null) or ($day == 'all')) {
            return redirect()->to(
                $userId . '/' . Time::today()->toDateString() . '/month'
            );
        }
        if ($period === null) {
            return redirect()->to($day . '/day');
        }

        switch ($period) {
            case 'week':
                return $this->time_list_week($userId, $day, $period);
                break;
            case 'month':
                return $this->time_list_month($userId, $day, $period);
                break;
            case 'day':
                return $this->time_list_day($userId, $day, $period);
                break;
            default:
                return $this->time_list_week($userId, $day, $period);
                break;
        }
    }

    public function perso_time($day = null, $period = null)
    {
        if ($this->is_admin()) {
            return redirect()->to('./users');
        } elseif (
            session()->get('user_access') == config('\User\Config\UserConfig')
            ->access_lvl_registered
        ) {
        } else {
            return;
        }

        if (!(session()->has('userIdAccess'))) {
            $model = model(AccessTimModel::class);
            $userId = $model->get_access_users($this->session->get('user_id'));
            switch (count($userId)) {
                case 0:
                    return $this->display_view('\User\errors\403error');
                    break;
                case 1:
                    $userId = $userId[0]['id_user'];
                    break;
                default:
                    return $this->access_user_list();
                    break;
            }

        } elseif (($day === null) and ($period === null)) {
            session()->remove('userIdAccess');
        } else {
            $model = model(AccessTimModel::class);
            $userId = session()->get('userIdAccess');
            $this->check_and_block_user();
        }


        if (($day === null)) {
            return redirect()->to(
                'perso_time/' . Time::today()->toDateString() . '/month'
            );
        }
        if ($period === null) {
            return redirect()->to($day . '/day');
        }

        return $this->perso_time_period($userId, $day, $period);

    }

    protected function perso_time_period($userId, $day, $period) {
        switch ($period) {
            case 'week':
                return $this->time_list_week($userId, $day, $period);
                break;
            case 'month':
                return $this->time_list_month($userId, $day, $period);
                break;
            case 'day':
                return $this->time_list_day($userId, $day, $period);
                break;
            default:
                return $this->time_list_week($userId, $day, $period);
                break;
        }

    }

    protected function is_session_access($id=null)
    {
        if (session()->has('userIdAccess')) {
            $model = model(AccessTimModel::class);
            $ciUserId = session()->get('user_id');
            $userId = session()->get('userIdAccess');
            return $model->is_access($ciUserId, $userId) or $this->is_admin();
        } else if (isset($id)) {
            $model = model(AccessTimModel::class);
            $ciUserId = session()->get('user_id');
            return $model->is_access($ciUserId, $id) or $this->is_admin();
        } else {
            return false or $this->is_admin();
        }
    }

    protected function is_admin()
    {
        return session()->get('user_access') == config(
            '\User\Config\UserConfig'
        )->access_lvl_admin;
    }

    protected function block_user()
    {
        session()->remove('userIdAccess');
        $this->display_view('\User\errors\403error');
        exit();
        return $this->display_view('\User\errors\403error');
    }

    protected function check_and_block_user($id = null)
    {
        if (!($this->is_session_access($id))) {
            return $this->block_user();
        }

    }


    protected function create_time_links($day, $period)
    {
        switch ($period) {
            case 'day':
                $past_day_str = $day->subDays(1)->toDateString();
                $after_day_str = $day->addDays(1)->toDateString();
                break;
            case 'month':
                if ($day->day > 28) {
                    # avoid skip a all month
                    $day = $day->setDay(28);
                }
                $past_day_str = $day->subMonths(1)->toDateString();
                $after_day_str = $day->addMonths(1)->toDateString();
                break;
            case 'week':
                $past_day_str = $day->subDays(7)->toDateString();
                $after_day_str = $day->addDays(7)->toDateString();
                break;
        }
        $past_link = '../' . $past_day_str .  '/' . $period;
        $after_link = '../' . $after_day_str .  '/' . $period;
        $buttons = [
            [
                'link' => $past_link,
                'label' => '<'
            ],
            [
                'link' => $after_link,
                'label' => '>'
            ],
        ];
        return $buttons;
    }

    protected function create_title($user, $day, $period)
    {
        $date = $day->toDateString();
        switch ($period) {
            case 'day':
                return $user['surname'] . ' ' . $user['name'] . ' ' . $date;
                break;
            case 'month':
                return $user['surname'] . ' ' . $user['name'] . ' mois ' .
                    $date;
                break;
            case 'week':
                return $user['surname'] . ' ' . $user['name'] . ' semaine ' .
                    $date;
                break;
            case 'all':
                return 'tous les logs ' . $user['surname'] . ' ' .
                    $user['name'];
                break;
        }
    }

    /**
     * @deprecated
     */
    private function perso_logs_list_old($userId, $day = null, $period = null)
    {
        trigger_error('Deprecated function called.', E_USER_DEPRECATED);
        if (($day === null) or ($day == 'all')) {
            return redirect()->to(
                $userId . '/' . Time::today()->toDateString() . '/all'
            );
        }
        if ($period === null) {
            return redirect()->to($day . '/day');
        }

        $user_data = $this->get_user_data($userId);
        $logs = $user_data['logs'];
        $user = $user_data['user'];

        $data['title'] = "Welcome";

        /**
         * Display a test of the generic "items_list" view (defined in common
         * module)
         */

        $data['columns'] = [
            'date' => 'Date',
            'id_badge' => 'Numéro du badge',
            'inside' => 'Entrée'
        ];
        $day = Time::parse($day);
        $data['period'] = $period;

        if ($period == 'all') {
            $data += $this->all_view($logs, $user);
        } elseif ($period == 'day') {
            $data += $this->day_view($logs, $user, $day);
        } elseif ($period == 'week') {
            $data += $this->week_view($logs, $user, $day);
        } elseif ($period == 'month') {
            $data += $this->month_view($logs, $user, $day);
        }
        $data['buttons'] += $this->create_buttons($period);


        $this->display_view(
            [
                'Timbreuse\Views\menu',
                'Timbreuse\Views\date', 'Common\Views\items_list'
            ],
            $data
        );
    }

    protected function create_buttons($period, bool $timeList = false)
    {
        $data = array();
        if (!$timeList) {
            array_push($data, [
                'link' => '../',
                'label' => ucfirst(lang('tim_lang.all'))
            ]);
        }
        if ($period != 'all') {
            array_push(
                $data,
                [
                    'link' => '../' . Time::today()->toDateString() . '/' .
                        $period,
                    'label' => ucfirst(lang('tim_lang.today')),
                ]
            );
        } else {
            array_push(
                $data,
                [
                    'link' => '../' . Time::today()->toDateString(),
                    'label' => ucfirst(lang('tim_lang.today')),
                ]
            );
        }
        array_push($data, [
            'link' => 'day',
            'label' => ucfirst(lang('tim_lang.day'))
        ]);
        array_push($data, [
            'link' => 'week',
            'label' => ucfirst(lang('tim_lang.week'))
        ]);
        array_push($data, [
            'link' => 'month',
            'label' => ucfirst(lang('tim_lang.month'))
        ]);
        return $data;
    }

    protected function get_user_data($userId)
    {
        $badgesModel = model(BadgesModel::class);
        $logsModel = model(LogsModel::class);
        $usersModel = model(UsersModel::class);
        $badgeId = $badgesModel->get_badges($userId);
        $data['logs'] = $logsModel->get_logs($badgeId);
        $data['user'] = $usersModel->get_users($userId);
        return $data;
    }



    /**
     * @deprecated
     */
    protected function month_view($logs, $user, $day)
    {
        trigger_error('Deprecated function called.', E_USER_DEPRECATED);
        $data['date'] = $day->toDateString();
        $data['list_title'] = $user['surname'] . ' ' . $user['name'] . ' mois '
            . $data['date'];
        $filter = function ($log) use ($day) {
            return $this->filter_log_month($log, $day);
        };
        $data['items'] = array_filter($logs, $filter);
        $data['buttons'] = [
            [
                'link' => '../' . $day->subDays(30)->toDateString() . '/month',
                'label' => '<'
            ],
            [
                'link' => '../' . $day->addDays(30)->toDateString() . '/month',
                'label' => '>'
            ],
        ];
        return $data;
    }

    /**
     * @deprecated
     */
    protected function week_view($logs, $user, $day)
    {
        trigger_error('Deprecated function called.', E_USER_DEPRECATED);
        $data['date'] = $day->toDateString();
        $data['list_title'] = $user['surname'] . ' ' . $user['name'] .
            ' semaine ' . $data['date'];
        $filter = function ($log) use ($day) {
            return $this->filter_log_week($log, $day);
        };
        $data['items'] = array_filter($logs, $filter);
        $data['buttons'] = [
            [
                'link' => '../' . $day->subDays(7)->toDateString() . '/week',
                'label' => '<'
            ],
            [
                'link' => '../' . $day->addDays(7)->toDateString() . '/week',
                'label' => '>'
            ],
        ];
        return $data;
    }

    /**
     * @deprecated
     */
    protected function day_view($logs, $user, $day)
    {
        trigger_error('Deprecated function called.', E_USER_DEPRECATED);
        $data['date'] = $day->toDateString();
        $data['list_title'] = $user['surname'] . ' ' . $user['name'] . ' ' .
            $data['date'];
        $filter = function ($log) use ($day) {
            return $this->filter_log_day($log, $day);
        };
        $data['items'] = array_filter($logs, $filter);
        $data['buttons'] = [
            [
                'link' => '../' . $day->subDays(1)->toDateString(),
                'label' => '<'
            ],
            [
                'link' => '../' . $day->addDays(1)->toDateString(),
                'label' => '>'
            ],
        ];
        return $data;
    }

    /**
     * @deprecated
     */
    protected function all_view($logs, $user)
    {
        trigger_error('Deprecated function called.', E_USER_DEPRECATED);
        $data['items'] = $logs;
        $data['list_title'] = "Tout les logs de" . ' ' . $user['surname'] .
            ' ' .
            $user['name'];
        $data['buttons'] = array();
        return $data;
    }

    protected function filter_log_month($log, Time $day)
    {
        $logDay = Time::parse($log['date']);
        return $this->is_same_month($day, $logDay);
    }

    protected function filter_log_week($log, Time $day)
    {
        $logDay = Time::parse($log['date']);
        return $this->is_same_week($day, $logDay);
    }

    protected function filter_log_day($log, Time $day)
    {
        $logDay = Time::parse($log['date']);
        return $this->is_same_day($day, $logDay);
    }

    protected function is_same_month(Time $day1, Time $day2)
    {
        $bMonths = $day1->month === $day2->month;
        $bYears = $day1->year === $day2->year;
        return $bMonths and $bYears;
    }

    protected function is_same_week(Time $day1, Time $day2)
    {
        $bWeek = $day1->getWeekOfYear() === $day2->getWeekOfYear();
        $bYears = $day1->year === $day2->year;
        return $bWeek and $bYears;
    }

    protected function is_same_day(Time $day1, Time $day2): bool
    {
        $bDay = $day1->day === $day2->day;
        $bMonths = $day1->month === $day2->month;
        $bYears = $day1->year === $day2->year;
        return $bDay and $bMonths and $bYears;
    }

    /**
     * @param bool $fakeLog choice to use model with log that created with the
     * website
     */
    protected function get_day_view_day_array(
        $userId,
        Time $date,
        bool $fakeLog = false
    ) {
        if (!$fakeLog) {
            $model = model(LogsModel::class);
        } else {
            $model = model(LogsFakeLogsModel::class);
        }
        $logs = $model->get_filtered_logs($userId, $date, 'day');
        return array_map(function ($log) {
            $data = array();
            $data['date'] = Time::parse($log['date']);
            $data['date'] = sprintf(
                '%02d:%02d:%02d',
                $data['date']->hour,
                $data['date']->minute,
                $data['date']->second
            );
            $data['time'] = $log['inside'] ? lang('tim_lang.enter') :
            lang('tim_lang.exit');
            $data['url'] = $this->get_url_for_get_day_view_day_array($log['id_fake_log']);
            return $data;
        }, $logs);
    }

    protected function get_url_for_get_day_view_day_array($fakeLogId){
            return isset($fakeLogId) ?  '../../detail_modify/' .
                $fakeLogId : null;
    }

    protected function get_time_day_by_period(
        $userId,
        Time $day,
        string $period,
        bool $fakeLog = false,
        bool $showAsterisk = false
    ): string {
        if (!$fakeLog) {
            $model = model(LogsModel::class);
        } else {
            $model = model(LogsFakeLogsModel::class);
        }
        $logs = $model->get_filtered_logs($userId, $day, $period);
        $time = $this->get_time_array($logs);
        $time = $this->get_hours_by_seconds($time);
        if ($showAsterisk) {
            return $this->is_fake_log($logs) ? $time . '✱' : $time;
        } else {
            return $time;
        }
    }

    public function access_user_list()
    {

        $ciUserId = $this->session->get('user_id');
        $model = model(AccessTimModel::class);
        $data['items'] = $model->get_access_users_with_info($ciUserId);
        $data['columns'] = [
            'name' => ucfirst(lang('tim_lang.name')),
            'surname' => ucfirst(lang('tim_lang.surname')),
        ];
        $data['primary_key_field']  = 'id_user';
        $data['url_detail'] = 'PersoLogs/access_user/';
        $this->display_view('Common\Views\items_list', $data);
        
    }

    public function access_user($userId)
    {
        $model = model(AccessTimModel::class);
        $ciUserId = $this->session->get('user_id');

        if ($model->is_access($ciUserId, $userId)) {
            session()->set('userIdAccess', $userId);
            $today = Time::today()->toDateString();
            return redirect()->to(
                './PersoLogs/perso_time/' . $today . '/month'
            );
        }
    }

    public function detail_modify($fakeLogFakeId){
        $data['fakeLogId'] = $fakeLogFakeId;
        $data['items'] = $this->get_items_array_detail_modify($fakeLogFakeId);
        $data['labels'] = array();
        $data['labels']['date'] = ucfirst(lang('tim_lang.hour'));
        $data['labels']['id_user'] = ucfirst(lang('tim_lang.username'));
        $data['labels']['inside'] = ucfirst(lang('tim_lang.enter'));
        $data['labels']['id_ci_user'] = ucfirst(lang('tim_lang.ciUsername'));
        $data['labels']['date_site'] = ucfirst(lang('tim_lang.modifyDate'));

        $data['buttons'] = array();
        $button = array();

        #$agent = $this->request->getUserAgent();
        #$button['link'] = $agent->getReferrer();
        $model = model(FakeLogsModel::class);
        $log = $model->find($fakeLogFakeId);
        $button['link'] = '../' . $this->redirect_log($log);

        #$button['link'] = $this->session->get('_ci_previous_url');
        $button['label'] = ucfirst(lang('tim_lang.back'));
        array_push($data['buttons'], $button);
        $this->display_view(
            [
                'Timbreuse\Views\menu',
                'Timbreuse\Views\logs\modify_log',
            ],
            $data
        );
    }

    protected function get_items_array_detail_modify($fakeLogFakeId = 1)
    {
        $model = model(FakeLogsModel::class);
        $items = $model->find($fakeLogFakeId);
        $this->check_and_block_user($items['id_user']);
        $items['inside'] = boolval($items['inside']) ? lang('tim_lang.yes') :
            lang('tim_lang.no');
        unset($items['id_fake_log']);
        $items['id_user'] = $this->get_username($items['id_user']);
        $items['id_ci_user'] = $this->get_site_username($items['id_ci_user']);
        return $items;
    }
    
    /**
     * duplicate from Users.php
     */
    protected function get_username($userId){
        $model = model(UsersModel::class);
        $userName = $model->select('name, surname')->find($userId);
        $userName = $userName['name'].' '.$userName['surname'];
        return $userName;
    }

    protected function get_site_username($ciUserId)
    {
        $model = model(UsersModel::class);
        $userName = $model->select('ci_user.username')->from('ci_user')
        ->where('ci_user.id', $ciUserId)->findall(1)[0]['username'];
        return $userName;
    }


    public function create_modify_log() {
        $this->check_and_block_user($this->request->getPost('userId'));
        $model = model(FakeLogsModel::class);
        if ($this->request->getMethod() === 'post' && $this->validate([
            'time' => 'required|regex_match[^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$]',
            'inside'  => 'required|regex_match[^(true|false)$]',
            'date' => 'required|valid_date',
            'userId' => 'required|integer',
        ])) {
            $request_array = array();
            $request_array['id_ci_user'] = $this->session->get('user_id');
            $request_array['id_user'] = $this->request->getPost('userId');
            $request_array['date'] = $this->request->getPost('date') . ' ' . $this->request->getPost('time');
            $request_array['inside'] = $this->request->getPost('inside') == 'true';
            $model->save($request_array);
            return redirect()->back();
        } else {
            return redirect()->back()->withInput();
        }
    }

    public function delete_modify_log($fakeLogId) {
        $model = model(FakeLogsModel::class);
        $data['userId'] = $model->find($fakeLogId)['id_user'];

        $data['id'] = $fakeLogId;
        $data['text'] = lang('tim_lang.confirmDelete');
        $data['link'] = '../confirm_delete_modify_log';
        $data['cancel_link'] = '../detail_modify/' . $fakeLogId;
        $data['label_button'] = ucfirst(lang('tim_lang.delete')); 
        $data['ciUserId'] = $this->session->get('user_id');

        $this->display_view('Timbreuse\Views\logs\confirm_delete', $data);
    
    }

    public function confirm_delete_modify_log() {
        if ($this->request->getMethod() === 'post') {
            $id = $this->request->getPost('id');
            $model = model(FakeLogsModel::class);
            $fakeLog = $model->find($id);
            $this->check_and_block_user($fakeLog['id_user']);
            $model->delete($id);
            $this->redirect_log($fakeLog);
            return redirect()->to($this->redirect_log($fakeLog));
        } else {
            $this->display_view('\User\errors\403error');
            exit();
            return $this->display_view('\User\errors\403error');
        }
    }

    protected function redirect_log(array $log) {
        $link = explode(' ', $log['date'])[0];
        $link .= '/day';
        $link = self::RETURN_METHOD_NAME . '/' . $link;
        return $link;
    }



    private function test1()
    {
        $model = model(LogsModel::class);
        $date = Time::parse('2022-05-20');
        var_dump($model->get_filtered_logs(92, $date, 'week'));
    }

    private function test2()
    {
        $time = Time::parse('1970-01-01');
        $time1 = Time::parse('1970-01-13');
        $diff = $time1->difference($time);
        var_dump($diff->seconds);
    }
    protected function test3()
    {
        $logs = array();
        $logs[0]['date'] = '2022-01-01 12:35';
        $logs[1]['date'] = '2022-01-01 12:50';
        $logs[2]['date'] = '2022-01-01 12:57';
        $logs[3]['date'] = '2022-01-01 13:00';
        $logs[4]['date'] = '2022-01-01 13:03';
        $logs[5]['date'] = '2022-01-02 08:03';
        $logs[6]['date'] = '2022-01-02 09:03';
        $logs[0]['inside'] = 1;
        $logs[1]['inside'] = 0;
        $logs[2]['inside'] = 1;
        $logs[3]['inside'] = 0;
        $logs[4]['inside'] = 1;
        $logs[5]['inside'] = 1;
        $logs[6]['inside'] = 0;
        $time = $this->get_time_array($logs);
        var_dump($time);
        // Expecting: 4680
    }
    private function test4()
    {
        $logs = array();
        $logs[0]['date'] = '2022-01-01 12:35';
        $logs[1]['date'] = '2022-01-01 12:50';
        $logs[2]['date'] = '2022-01-01 12:57';
        $logs[3]['date'] = '2022-01-01 13:00';
        $logs[4]['date'] = '2022-01-01 13:03';
        $logs[5]['date'] = '2022-01-02 08:03';
        $logs[6]['date'] = '2022-01-02 09:03';
        $logs[7]['date'] = '2022-01-03 08:03';
        $logs[8]['date'] = '2022-01-03 09:03';
        $logs[0]['inside'] = 1;
        $logs[1]['inside'] = 0;
        $logs[2]['inside'] = 1;
        $logs[3]['inside'] = 0;
        $logs[4]['inside'] = 1;
        $logs[5]['inside'] = 1;
        $logs[6]['inside'] = 0;
        $logs[7]['inside'] = 1;
        $logs[8]['inside'] = 0;
        $time = $this->get_time_array($logs);
        var_dump($time);
        // Expecting: 8280
    }

    private function test5()
    {
        $day = '2022-05-18';
        $day = Time::parse($day);
        $halfDay = 'morning';
        $halfDay = 'afternoon';
        $userId = '92';
        $model = model(LogsModel::class);
        var_dump($model->get_logs_by_period($userId, $day, $halfDay));
    }

    private function test6()
    {
        $day = '2022-05-18';
        $day = Time::parse($day);
        var_dump($this->get_last_monday($day));
    }

    private function test7()
    {
        $days[0] = '2022-05-18';
        $days[1] = '2022-05-18';
        $days[2] = '2022-05-18';
        $days[3] = '2022-05-18';
        $days[4] = '2022-05-18';
        foreach ($days as $i => $day) {
            $day = Time::parse($day);
            var_dump($this->get_last_monday($day)->addDays($i));
        }
    }

    private function test8()
    {
        $day = '2022-05-18';
        $day = Time::parse($day);
        $model = model(LogsModel::class);
        var_dump($model->get_border_log_by_period('92', $day, 'morning', true));
        /* Expecting:
            array (size=3)
                'date' => string '2022-05-18 12:00:28' (length=19)
                'id_badge' => string '589402514225' (length=12)
                'inside' => string '0' (length=1)
        */
    }

    private function test9()
    {
        $day = '2022-05-18';
        $day = Time::parse($day);
        $model = model(LogsModel::class);
        var_dump($model->get_day_time_table('92', $day, 'morning'));
        /* Expecting:
            array (size=3)
                'time' => string '4:02:01' (length=7)
                'first' => string '07:58:27' (length=8)
                'last' => string '12:00:28' (length=8)
        */
    }

    private function test10()
    {
        $day = '2022-05-18';
        $day = Time::parse($day);
        $model = model(LogsModel::class);
        var_dump($model->get_upper_day_time_table('92', $day));
        /* Expecting:
            array (size=3)
                'dayNb' => string '18' (length=2)
                'morning' => 
                    array (size=3)
                        'time' => string '4:02:01' (length=7)
                        'first' => string '07:58:27' (length=8)
                        'last' => string '12:00:28' (length=8)
                'afternoon' => 
                    array (size=3)
                        'time' => string '4:16:02' (length=7)
                        'first' => string '12:31:27' (length=8)
                        'last' => string '16:47:29' (length=8)
            */
    }

    private function test11()
    {
        $day = '2022-05-18';
        $day = Time::parse($day);
        $model = model(LogsModel::class);
        var_dump($model->get_week_time_table('92', $day));
    }

    private function test12()
    {
        $day = '2022-05-18';
        $day = Time::parse($day);
        $model = model(LogsModel::class);
        var_dump($model->get_day_week_array('92', $day));
    }

    private function test13()
    {
        $day = '2022-05-18';
        $day = Time::parse($day);
        $model = model(LogsModel::class);
        var_dump($model->get_month_week_array('92', $day));
    }

    private function test14()
    {
        $day = '2022-05-18 12:11:22';
        $day = Time::parse($day);
        var_dump($this->get_day_view_day_array('92', $day));
    }

    private function test15()
    {
        $day = '2022-05-18 12:11:22';
        $day = Time::parse($day);
        var_dump($this->get_time_day_by_period('92', $day, 'week'));
    }

    private function test16()
    {
        $model = model(AccessTimModel::class);
        var_dump($model->is_access(8, 92));
    }

    public function test17()
    {
        $model = model(LogsFakeLogsModel::class);
        $day = Time::parse('2022-05-31');
        var_dump($model->get_border_log_by_period(92, $day, 'morning', true));
    }

    public function test18()
    {
        $data['items'] = array();
        $data['items'][0]['label'] = 'test';
        $data['items'][0]['data'] = 'data';
        $data['items'][1]['label'] = 'test2';
        $data['items'][1]['data'] = 'data2';
        $data['items'][2]['label'] = 'test3';
        $data['items'][2]['data'] = 'data3';
        $data['list_title'] = 'test';
        $this->display_view('Timbreuse\Views\logs\modify_log', $data);
    }

    public function test19()
    {
        $day = Time::parse('2022-05-30');
        var_dump($this->get_day_view_day_array(92, $day));
        var_dump($this->get_day_view_day_array(92, $day, true));
    }
}
