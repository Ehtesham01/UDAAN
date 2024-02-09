<?php
session_start();

if (isset($_POST['pass_but']) && isset($_SESSION['userId'])) {
    require '../helpers/init_conn_db.php';

    // Validate mobile number length
    $mobile_flag = false;
    foreach ($_POST['mobile'] as $mobile) {
        if (strlen($mobile) !== 10) {
            $mobile_flag = true;
            break;
        }
    }

    if ($mobile_flag) {
        header('Location: ../pass_form.php?error=moblen');
        exit();
    }

    // Validate date of birth
    $date_len = count($_POST['date']);
    $current_date = new DateTime();

    for ($i = 0; $i < $date_len; $i++) {
        $dob = new DateTime($_POST['date'][$i]);

        if ($dob > $current_date) {
            header('Location: ../pass_form.php?error=invdate');
            exit();
        }
    }

    // Set $flight_id - assuming it comes from the form
    $flight_id = isset($_POST['flight_id']) ? $_POST['flight_id'] : null;

    if ($flight_id === null) {
        header('Location: ../pass_form.php?error=missingflightid');
        exit();
    }

    // Check existing passenger profiles
    $stmt = mysqli_stmt_init($conn);
    $sql = 'SELECT * FROM Passenger_profile WHERE flight_id = ? AND user_id = ?';

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header('Location: ../pass_form.php?error=sqlerror');
        exit();
    } else {
        mysqli_stmt_bind_param($stmt, 'is', $flight_id, $_SESSION['userId']);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $pass_id = null;

        while ($row = mysqli_fetch_assoc($result)) {
            $pass_id = $row['passenger_id'];
        }
    }

    if (is_null($pass_id)) {
        // Reset the auto-increment to 1 if no existing profiles
        $stmt = mysqli_stmt_init($conn);
        $sql = 'ALTER TABLE Passenger_profile AUTO_INCREMENT = 1 ';

        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header('Location: ../pass_form.php?error=sqlerror');
            exit();
        } else {
            mysqli_stmt_execute($stmt);
        }
    }

    // Insert passenger profiles
    $stmt = mysqli_stmt_init($conn);
    $flag = false;

    for ($i = 0; $i < $date_len; $i++) {
        $sql = 'INSERT INTO Passenger_profile (user_id, mobile, dob, f_name, m_name, l_name, flight_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)';

        if (!mysqli_stmt_prepare($stmt, $sql)) {
            header('Location: ../pass_form.php?error=sqlerror');
            exit();
        } else {
            mysqli_stmt_bind_param($stmt, 'iissssi', $_SESSION['userId'],
                $_POST['mobile'][$i], $_POST['date'][$i], $_POST['firstname'][$i],
                $_POST['midname'][$i], $_POST['lastname'][$i], $flight_id);
            mysqli_stmt_execute($stmt);
            $flag = true;
        }
    }

    if ($flag) {
        // Set session variables and redirect to payment.php
        $_SESSION['flight_id'] = $flight_id;
        $_SESSION['class'] = $_POST['class'];
        $_SESSION['passengers'] = $passengers;
        $_SESSION['price'] = $_POST['price'];
        $_SESSION['type'] = $_POST['type'];
        $_SESSION['ret_date'] = $_POST['ret_date'];
        $_SESSION['pass_id'] = $pass_id + 1;
        header('Location: ../payment.php');
        exit();
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    // Redirect to pass_form.php in case of any issues
    header('Location: ../pass_form.php');
    exit();
}
?>
