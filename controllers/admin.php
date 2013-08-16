<?php

require_once (dirname(__FILE__) . '/../models/user.php');
require_once (dirname(__FILE__) . '/../models/room.php');
require_once (dirname(__FILE__) . '/../models/history.php');
require_once (dirname(__FILE__) . '/../models/participant.php');
require_once (dirname(__FILE__) . '/../include/utils.php');

/**
 * Ajax request dispatcher 
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    switch ($_POST['page']) {
    case 'dashboard':
        dashboard();
        break;
    case 'rooms':
        rooms();
        break;
    case 'users':
        users();
        break;
    case 'settings':
        settings();
        break;
    }
}


function dashboard() {
    sr_response('views/admin/dashboard.php', null);
}


function rooms() {
    // Show Rooms Page
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        $db = sr_pdo();

        $stmt = $db->prepare('SELECT * FROM room LIMIT 10');
        $stmt->execute();

        $room_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'Room');

        $stmt = $db->prepare('SELECT * FROM history LIMIT 10');
        $stmt->execute();

        $history_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'History');

        $context = array(
            'room_list' => $room_list,
            'history_list' => $history_list
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
                    $total_record_number = History::getRecordNum($filter);
                }

                if ($_POST['page_number'] == -1) {
                    $beginRecordNum = (int)($total_record_number / 10) * 10 + 1;
                } else {
                    $beginRecordNum = ($_POST['page_number'] - 1) * 10;
                }

                if ($_POST['table'] == 't1') {
                    $stmt = $db->prepare("SELECT * FROM room $where LIMIT $beginRecordNum, 10");
                    $stmt->execute();

                    $record_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'Room');
                } else {
                    $stmt = $db->prepare("SELECT * FROM history $where LIMIT $beginRecordNum, 10");
                    $stmt->execute();

                    $record_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'History');
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
            // TODO: Write History
            try {
                $db = sr_pdo();

                $stmt = $db->prepare('SELECT * FROM room WHERE id = :id');
                $stmt->bindParam(':id', $_POST['id']);
                $stmt->setFetchMode(PDO::FETCH_CLASS, 'Room');
                $stmt->execute();

                $user = $stmt->fetch();

                $result = $user->close($db);

            } catch (PDOException $e) {

            }
        }
    }
}


function users() {
    // Show Users Page
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        $db = sr_pdo();

        $stmt = $db->prepare('SELECT * FROM user LIMIT 10');
        $stmt->execute();

        $user_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'User');

        $context = array(
            'user_list' => $user_list
        );

        sr_response('views/admin/users.php', $context);

    // Handling Ajax Request
        //
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
                    $beginRecordNum = (int)($total_record_number / 10) * 10 + 1;
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

            } catch (PDOException $e) {

            }
        }
    }
}


function settings() {
    sr_response('views/admin/settings.php', null);
}


/**
 * Display room list.
 */
function admin_room_list() {
    $db = sr_pdo();

    $stmt = $db->prepare('SELECT * FROM room');
    $stmt->execute();

    $room_list = $stmt->fetchAll(PDO::FETCH_CLASS, 'Room');

    $context = array(
        'room_list' => $room_list,
    );

    sr_response('views/admin/room/room_list.php', $context);
}

?>
