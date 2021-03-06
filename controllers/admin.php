<?php

require_once (dirname(__FILE__) . '/../models/user.php');
require_once (dirname(__FILE__) . '/../models/room.php');
require_once (dirname(__FILE__) . '/../models/room_log.php');
require_once (dirname(__FILE__) . '/../models/participant.php');
require_once (dirname(__FILE__) . '/../models/participant_log.php');
require_once (dirname(__FILE__) . '/../include/utils.php');
require_once (dirname(__FILE__) . '/../settings/config.php');

/**
 * Ajax request dispatcher 
 */
function admin_ajax_dispatcher() {
    switch ($_POST['page']) {
    case 'dashboard':
        admin_dashboard();
        break;
    case 'rooms':
        admin_rooms();
        break;
    case 'users':
        admin_users();
        break;
    case 'settings':
        admin_settings();
        break;
    }
}


function admin_dashboard() {
    global $sr_channel_server_uri;
    global $sr_root;
    global $sr_channel_local_installation;

    // Show Dashboard Page
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        if (!sr_is_signed_in()) {
            sr_redirect('/d/main/signin/');
        }
        if (!sr_is_admin()) {
            sr_redirect('/d/');
        }

        $db = sr_pdo();

        $room_log_data = array();

        for ($i = 0; $i > -8; $i--) {
            $date = date('Y-m', strtotime($i . ' month'));
            $a_month_data = array();
            $a_month_data['period'] = $date;

            for ($j = 0; $j < 3; $j++) {
                $filter = 'is_open=' . $j . ' AND';
                if ($j == 2) {
                    $filter = '';
                }

                $stmt = $db->prepare("SELECT COUNT(*) FROM room_log
                    WHERE $filter DATE_FORMAT(start_time, '%Y-%m') BETWEEN '$date' AND '$date'");
                $stmt->execute();

                $result = $stmt->fetch();

                switch ($j) {
                case 0: $a_month_data['private'] = $result['COUNT(*)']; break;
                case 1: $a_month_data['public'] = $result['COUNT(*)']; break;
                case 2: $a_month_data['total'] = $result['COUNT(*)']; break;
                }
            }

            array_push($room_log_data, $a_month_data);
        }

        $participant_log_data = array();

        for ($i = 0; $i > -8; $i--) {
            $date = date('Y-m', strtotime($i . ' month'));
            $a_month_data = array();
            $a_month_data['period'] = $date;

            for ($j = 0; $j < 3; $j++) {
                $filter = 'is_registered_user=' . $j . ' AND ';
                if ($j == 2) {
                    $filter = '';
                }
                $filter .= 'type=2 AND';

                $stmt = $db->prepare("SELECT COUNT(*) FROM participant_log
                    WHERE $filter DATE_FORMAT(time, '%Y-%m') BETWEEN '$date' AND '$date'");
                $stmt->execute();

                $result = $stmt->fetch();

                switch ($j) {
                case 0: $a_month_data['non-member'] = $result['COUNT(*)']; break;
                case 1: $a_month_data['member'] = $result['COUNT(*)']; break;
                case 2: $a_month_data['total'] = $result['COUNT(*)']; break;
                }
            }

            array_push($participant_log_data, $a_month_data);
        }

        $room_num_data = array(
            'total' => RoomLog::getRecordNum(array()),
            'current' => Room::getRecordNum(array()),
        );

        $filter = array();
        $filter['type'] = 2;

        $participant_num_data = array(
            'total' => ParticipantLog::getRecordNum($filter),
            'current' => Participant::getRecordNum(array()),
        );

        $context = array(
            'room_log_data' => $room_log_data,
            'room_num_data' => $room_num_data,
            'participant_log_data' => $participant_log_data,
            'participant_num_data' => $participant_num_data,
            'channel_server_uri' => $sr_channel_server_uri,
            'channel_server_control_api' => $sr_root . '/d/admin/channel',
            'show_channel_server_controls' => $sr_channel_local_installation,
        );

        sr_response('views/admin/dashboard.php', $context);

    // Handling Ajax Request (Pagination)
    } else {
        try {
            $db = sr_pdo();

            $log_data = array();

            if ($_POST['selected_btn'] == 'prev') {
                $base_date = date('Y-m', strtotime('6 month', strtotime($_POST['viewed_date_first'])));
            } else {
                $base_date = date('Y-m', strtotime('8 month', strtotime($_POST['viewed_date_first'])));
            }

            for ($i = 0; $i > -8; $i--) {
                $date = date('Y-m', strtotime($i . ' month', strtotime($base_date)));
                $a_month_data = array();
                $a_month_data['period'] = $date;

                for ($j = 0; $j < 3; $j++) {
                    if ($_POST['graph'] == 'room') {
                        $filter = 'is_open=' . $j . ' AND';
                        if ($j == 0) {
                            $filter = '';
                        }

                        $stmt = $db->prepare("SELECT COUNT(*) FROM room_log
                            WHERE $filter DATE_FORMAT(start_time, '%Y-%m') BETWEEN '$date' AND '$date'");
                        $stmt->execute();

                        $result = $stmt->fetch();

                        switch ($j) {
                        case 0: $a_month_data['total'] = $result['COUNT(*)']; break;
                        case 1: $a_month_data['public'] = $result['COUNT(*)']; break;
                        case 2: $a_month_data['private'] = $result['COUNT(*)']; break;
                        }
                    } else {
                        $filter = 'is_registered_user=' . $j . ' AND ';
                        if ($j == 2) {
                            $filter = '';
                        }
                        $filter .= 'type=2 AND';

                        $stmt = $db->prepare("SELECT COUNT(*) FROM participant_log
                            WHERE $filter DATE_FORMAT(time, '%Y-%m') BETWEEN '$date' AND '$date'");
                        $stmt->execute();

                        $result = $stmt->fetch();

                        switch ($j) {
                        case 0: $a_month_data['non-member'] = $result['COUNT(*)']; break;
                        case 1: $a_month_data['member'] = $result['COUNT(*)']; break;
                        case 2: $a_month_data['total'] = $result['COUNT(*)']; break;
                        }
                    }
                }

                array_push($log_data, $a_month_data);
            }

            if ($_POST['graph'] == 'room') {
                $num_data = array(
                    'total' => RoomLog::getRecordNum(array()),
                    'current' => Room::getRecordNum(array()),
                );
            } else {
                $filter = array();
                $filter['type'] = 2;

                $num_data = array(
                    'total' => ParticipantLog::getRecordNum($filter),
                    'current' => Participant::getRecordNum(array()),
                );
            }

            $result = array(
                'log_data' => $log_data,
                'num_data' => $num_data,
            );

            echo json_encode($result);

        } catch (PDOException $e) {

        }
    }
}


function admin_rooms() {
    global $sr_default_chat_name;
    
    // Show Rooms Page
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        if (!sr_is_signed_in()) {
            sr_redirect('/d/main/signin/');
        }
        if (!sr_is_admin()) {
            sr_redirect('/d/');
        }

        $db = sr_pdo();

        $stmt = $db->prepare('SELECT * FROM room ORDER BY start_time DESC LIMIT 10');
        $stmt->execute();

        $room_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'Room');

        foreach ($room_list as $a_room) {
            $room_id = $a_room->id;
            $stmt = $db->prepare("SELECT name FROM participant WHERE room_id='$room_id'");
            $stmt->execute();

            $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $a_room->participants = '';

            foreach ($participants as $a_participant) {
                if ($a_participant == '') {
                    $a_participant = $sr_default_chat_name;
                }
                $a_room->participants .= $a_participant . '<br />';
            }
        }

        $stmt = $db->prepare('SELECT * FROM room_log ORDER BY id DESC LIMIT 10');
        $stmt->execute();

        $room_log_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'RoomLog');

        foreach ($room_log_list as $a_room_log) {
            $room_id = $a_room_log->room_id;
            $stmt = $db->prepare("SELECT participant_name FROM participant_log WHERE type=2 AND room_id='$room_id'");
            $stmt->execute();

            $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $a_room_log->participants = '';

            foreach ($participants as $a_participant) {
                if ($a_participant == '') {
                    $a_participant = $sr_default_chat_name;
                }
                $a_room_log->participants .= $a_participant . '<br />';
            }
        }

        $context = array(
            'room_list' => $room_list,
            'room_log_list' => $room_log_list
        );

        sr_response('views/admin/rooms.php', $context);

    // Handling Ajax Request
    } else {
        // Pagination or Filtering
        if ($_POST['type'] == 'pagination') {
            try {
                $db = sr_pdo();

                $json = $_POST['filter'];
                $json = stripslashes($json);
                $filter = json_decode($json);

                $where = '';
                $index = 0;
                foreach ($filter as $field => $value) {
                    if ($index++ == 0) {
                        $where .= 'WHERE ';
                    } else {
                        $where .= ' AND ';
                    }
                    $where .= $field . '=' . $value;
                }

                if ($_POST['table'] == 't1') {
                    $total_record_number = Room::getRecordNum($filter);
                } else {
                    $total_record_number = RoomLog::getRecordNum($filter);
                }

                if ($_POST['page_number'] == -1) {
                    $beginRecordNum = (int)($total_record_number / 10) * 10;
                } else {
                    $beginRecordNum = ($_POST['page_number'] - 1) * 10;
                }

                if ($_POST['table'] == 't1') {
                    $stmt = $db->prepare("SELECT * FROM room $where ORDER BY start_time DESC LIMIT $beginRecordNum, 10");
                    $stmt->execute();

                    $record_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'Room');

                    foreach ($record_list as $a_room) {
                        $room_id = $a_room->id;
                        $stmt = $db->prepare("SELECT name FROM participant WHERE room_id='$room_id'");
                        $stmt->execute();

                        $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        $a_room->participants = '';

                        foreach ($participants as $a_participant) {
                            if ($a_participant == '') {
                                $a_participant = $sr_default_chat_name;
                            }
                            $a_room->participants .= $a_participant . '<br />';
                        }
                    }
                } else {
                    $stmt = $db->prepare("SELECT * FROM room_log $where ORDER BY id DESC LIMIT $beginRecordNum, 10");
                    $stmt->execute();

                    $record_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'RoomLog');

                    foreach ($record_list as $a_room_log) {
                        $room_id = $a_room_log->room_id;
                        $stmt = $db->prepare("SELECT participant_name FROM participant_log WHERE type=2 AND room_id='$room_id'");
                        $stmt->execute();

                        $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        $a_room_log->participants = '';

                        foreach ($participants as $a_participant) {
                            if ($a_participant == '') {
                                $a_participant = $sr_default_chat_name;
                            }
                            $a_room_log->participants .= $a_participant . '<br />';
                        }
                    }
                }

                $result = array(
                    'record_list' => $record_list,
                    'total_record_number' => $total_record_number
                );

                echo json_encode($result);

            } catch (PDOException $e) {

            }

        // Close Room Request
        } else {
            try {
                $db = sr_pdo();

                $stmt = $db->prepare('SELECT * FROM room WHERE id = :id');
                $stmt->bindParam(':id', $_POST['id']);
                $stmt->setFetchMode(PDO::FETCH_CLASS, 'Room');
                $stmt->execute();

                $room = $stmt->fetch();

                $roomLog = new RoomLog();
                $roomLog->room_id       = $room->id;
                $roomLog->name          = $room->name;
                $roomLog->title         = $room->title;
                $roomLog->description   = $room->description;
                $roomLog->is_open       = $room->is_open;
                $roomLog->start_time    = $room->start_time;
                $roomLog->end_time      = Model::getCurrentTime();
                $roomLog->add($db);

                $room->close($db);

            } catch (PDOException $e) {

            }
        }
    }
}


function admin_users() {
    // Show Users Page
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        if (!sr_is_signed_in()) {
            sr_redirect('/d/main/signin/');
        }
        if (!sr_is_admin()) {
            sr_redirect('/d/');
        }

        $db = sr_pdo();

        $stmt = $db->prepare('SELECT * FROM user LIMIT 10');
        $stmt->execute();

        $user_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'User');

        $context = array(
            'user_list' => $user_list
        );

        sr_response('views/admin/users.php', $context);

    // Handling Ajax Request
    } else {
        // Pagination or Filtering
        if ($_POST['type'] == 'pagination') {
            try {
                $db = sr_pdo();

                $json = $_POST['filter'];
                $json = stripslashes($json);
                $filter = json_decode($json);

                $where = '';
                $index = 0;
                foreach ($filter as $field => $value) {
                    if ($index++ == 0) {
                        $where .= 'WHERE ';
                    } else {
                        $where .= ' AND ';
                    }
                    $where .= $field . '=' . $value;
                }

                $total_record_number = User::getRecordNum($filter);

                if ($_POST['page_number'] == -1) {
                    $beginRecordNum = (int)($total_record_number / 10) * 10;
                } else {
                    $beginRecordNum = ($_POST['page_number'] - 1) * 10;
                }

                $stmt = $db->prepare("SELECT * FROM user $where LIMIT $beginRecordNum, 10");
                $stmt->execute();

                $user_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'User');

                $result = array(
                    'user_list' => $user_list,
                    'total_record_number' => $total_record_number
                );

                echo json_encode($result);

            } catch (PDOException $e) {

            }
        // Update Authorized or Admin Authority
        } else {
            try {
                $db = sr_pdo();

                $stmt = $db->prepare('SELECT * FROM user WHERE id = :id');
                $stmt->bindParam(':id', $_POST['id']);
                $stmt->setFetchMode(PDO::FETCH_CLASS, 'User');
                $stmt->execute();

                $user = $stmt->fetch();

                if ($_POST['type'] == 'authorized') {
                    if ($_POST['checked'] == 'checked') {
                        $user->is_authorized = 1;
                    } else {
                        $user->is_authorized = 0;
                    }
                } else {
                    if ($_POST['checked'] == 'checked') {
                        $user->is_admin = 1;
                    } else {
                        $user->is_admin = 0;
                    }
                }

                $result = $user->save($db);

                if (sr_user_id() == $user->id) {
                    sr_set_admin($user->is_admin);
                    sr_set_authorized($user->is_authorized);
                }


            } catch (PDOException $e) {

            }
        }
    }
}

function admin_settings() {
    if (!sr_is_signed_in()) {
        sr_redirect('/d/main/signin/');
    }
    if (!sr_is_admin()) {
        sr_redirect('/d/');
    }

    global $sr_root;
    global $sr_db_type;
    global $sr_db_host;
    global $sr_db_port;
    global $sr_db_name;
    global $sr_db_user;
    global $sr_db_password;
    global $sr_db_charset;
    global $sr_email_addr;
    global $sr_email_smtp;
    global $sr_default_authority;
    global $sr_join_anonymous;
    global $sr_join_non_authorized;
    global $sr_main_content;

    $sr_email_addr = htmlentities($sr_email_addr);

    $context = array(
        'db_type'       => $sr_db_type,
        'db_host'       => $sr_db_host,
        'db_port'       => $sr_db_port,
        'db_database'   => $sr_db_name,
        'db_username'   => $sr_db_user,
        'db_password'   => $sr_db_password,
        'db_char_set'   => $sr_db_charset,

        'default_authority'     => $sr_default_authority ? 'Yes' : 'No',
        'join_anonymous'        => $sr_join_anonymous ? 'Yes' : 'No',
        'join_non_authorized'   => $sr_join_non_authorized ? 'Yes' : 'No',

        'smtp_email_addr'   => $sr_email_addr,
        'smtp_server'       => $sr_email_smtp['host'],
        'smtp_port'         => $sr_email_smtp['port'],
        'smtp_username'     => $sr_email_smtp['username'],

        'installation_path' => $sr_root,

        'maximum_users'     => 'TODO',
        'stun_server'       => 'TODO',
        'xmpp_server_use'   => 'TODO',
        'xmpp_server'       => 'TODO',

        'main_content'      => $sr_main_content,
    );

    sr_response('views/admin/settings.php', $context);
}

function admin_channel_start() {
    global $sr_root;
    global $sr_channel_run_script;
    global $sr_channel_local_installation;
    global $sr_channel_log_file;

    $result = array();
    if ($sr_channel_local_installation) {
        $res = shell_exec('nohup php ' . $_SERVER['DOCUMENT_ROOT'] . $sr_root . $sr_channel_run_script . ' >> ' . $sr_channel_log_file . ' &');
        $result['result'] = 0;

    } else {
        $result['result'] = 1;
        $result['msg'] = 'You cannot start the channel server on the admin page.';
    }
    echo json_encode($result);
}

function admin_channel_stop() {
    global $sr_root;
    global $sr_channel_run_script;
    global $sr_channel_local_installation;

    if ($sr_channel_local_installation) {
        exec('pgrep -fx "php ' . $_SERVER['DOCUMENT_ROOT'] . $sr_root . $sr_channel_run_script . '"', $pids);

        $result = array();

        if (count($pids) > 0) {
            // channel server running
            foreach ($pids as $pid) {
                posix_kill($pid, 9);
            }
            $result['result'] = 0;

        } else {
            // channel server is not running 
            $result['result'] = 2;
            $result['msg'] = "Couldn't find the sunrise channel server process.";
        }
    } else {
        $result['result'] = 1;
        $result['msg'] = 'You cannot stop the channel server on the admin page.';
    }

    echo json_encode($result);
}

function admin_channel_restart() {
    global $sr_root;
    global $sr_channel_run_script;
    global $sr_channel_local_installation;
    global $sr_channel_log_file;

    $result = array();
    if ($sr_channel_local_installation) {
        exec('pgrep -f ' . $_SERVER['DOCUMENT_ROOT'] . $sr_root . $sr_channel_run_script, $pids);

        $cnt = count($pids);
        $result = array();

        if ($cnt > 1) {
            // channel server running
            for ($i = 0; $i < $cnt - 1; $i++) {
                posix_kill($pids[$i], 9);
            }
        }

        $res = shell_exec('nohup php ' . $_SERVER['DOCUMENT_ROOT'] . $sr_root . $sr_channel_run_script . ' >> ' . $sr_channel_log_file . ' &');
        $result['result'] = 0;

    } else {
        $result['result'] = 1;
        $result['msg'] = 'You cannot restart the channel server on the admin page.';
    }

    echo json_encode($result);
}

?>
