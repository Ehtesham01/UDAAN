<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;

if (isset($_POST['pay_but']) && isset($_SESSION['userId'])) {
    require '../helpers/init_conn_db.php';

    $flight_id = $_SESSION['flight_id'];
    $price = $_SESSION['price'];
    $passengers = $_SESSION['passengers'];
    $pass_id = $_SESSION['pass_id'];
    $type = $_SESSION['type'];
    $class = $_SESSION['class'];
    $ret_date = $_SESSION['ret_date'];
    $card_no = $_POST['cc-number'];
    $expiry = $_POST['cc-exp'];

    //$conn = mysqli_connect("localhost","admin","codeastro.com","ofbsphp");

    ///if (!$conn) {
     //   die("Connection failed: " . mysqli_connect_error());
   // }

    // Insert payment information
    $payment_sql = 'INSERT INTO PAYMENT (user_id, expire_date, amount, flight_id, card_no) 
                    VALUES (?, ?, ?, ?, ?)';
    $payment_stmt = mysqli_prepare($conn, $payment_sql);

    if (!$payment_stmt) {
        handleSqlError($conn);
    }

    mysqli_stmt_bind_param($payment_stmt, 'isiis', $_SESSION['userId'], $expiry, $price, $flight_id, $card_no);
    mysqli_stmt_execute($payment_stmt);

    // Update seat information
    $update_seat_sql = ($class === 'B')
        ? "UPDATE Flight SET last_bus_seat=?, bus_seats=? WHERE flight_id=?"
        : "UPDATE Flight SET last_seat=?, Seats=? WHERE flight_id=?";

    $update_seat_stmt = mysqli_prepare($conn, $update_seat_sql);

    if (!$update_seat_stmt) {
        handleSqlError($conn);
    }

    $flag = false;

    for ($i = $pass_id; $i <= $passengers + $pass_id; $i++) {
        // Select flight information
        $select_flight_sql = 'SELECT * FROM Flight WHERE flight_id=?';
        $select_flight_stmt = mysqli_prepare($conn, $select_flight_sql);

        if (!$select_flight_stmt) {
            handleSqlError($conn);
        }

        mysqli_stmt_bind_param($select_flight_stmt, 'i', $flight_id);
        mysqli_stmt_execute($select_flight_stmt);

        $result = mysqli_stmt_get_result($select_flight_stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            $source = $row['source'];
            $dest = $row['Destination'];
            $new_seat = '';

            // Logic to generate new seat
            // ...

            $seats = ($class === 'B') ? $row['bus_seats'] : $row['Seats'];
            $seats = $seats - 1;

            // Update seat information
            mysqli_stmt_bind_param($update_seat_stmt, 'sii', $new_seat, $seats, $flight_id);
            mysqli_stmt_execute($update_seat_stmt);

            // Insert ticket information
            $insert_ticket_sql = 'INSERT INTO Ticket (passenger_id, flight_id, seat_no, cost, class, user_id)
                                VALUES (?, ?, ?, ?, ?, ?)';
            $insert_ticket_stmt = mysqli_prepare($conn, $insert_ticket_sql);

            if (!$insert_ticket_stmt) {
                handleSqlError($conn);
            }

            mysqli_stmt_bind_param($insert_ticket_stmt, 'iisisi', $i, $flight_id, $new_seat, $price, $class, $_SESSION['userId']);
            mysqli_stmt_execute($insert_ticket_stmt);

            $flag = true;
        } else {
            handleSqlError($conn);
        }
    }

    // Process round trip if applicable
    if ($type === 'round' && $flag === true) {
        // Logic for round trip
        // ...
    }

    mysqli_stmt_close($payment_stmt);
    mysqli_stmt_close($update_seat_stmt);
    mysqli_stmt_close($select_flight_stmt);
    mysqli_stmt_close($insert_ticket_stmt);

    mysqli_close($conn);

    if ($flag) {
        unsetSessionVariables();
        header('Location: ../pay_success.php');
        exit();
    } else {
        header('Location: ../payment.php?error=sqlerror');
        exit();
    }
} else {
    header('Location: ../payment.php');
    exit();
}

// Function to handle SQL errors
function handleSqlError($connection) {
    mysqli_close($connection);
    header('Location: ../payment.php?error=sqlerror');
    exit();
}

// Function to unset session variables
function unsetSessionVariables() {
    unset($_SESSION['flight_id']);
    unset($_SESSION['passengers']);
    unset($_SESSION['pass_id']);
    unset($_SESSION['price']);
    unset($_SESSION['class']);
    unset($_SESSION['type']);
    unset($_SESSION['ret_date']);
}
?>
