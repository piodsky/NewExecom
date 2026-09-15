<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Requested-With');

// Get the JSON data from the request body and decode it
$json = file_get_contents('php://input');
$params = json_decode($json);

// Validate and sanitize the input value (FindRollNo)
$FindRollNo = isset($params->FindRollNo) ? filter_var($params->FindRollNo, FILTER_SANITIZE_STRING) : '';

// First database connection
$CN = mysqli_connect("192.168.10.234", "onlineadmin", "admis2024");

if (!$CN) {
    $response = array('status' => 'error', 'message' => 'Failed to connect to the database: ' . mysqli_connect_error());
    echo json_encode($response);
    exit;
}


// Select the database
$DB = mysqli_select_db($CN, "online_stats");

if (!$DB) {
    $response = array('status' => 'error', 'message' => 'Failed to select the database: ' . mysqli_error($CN));
    echo json_encode($response);
    mysqli_close($CN);
    exit;
}

// Get the user's IP address using the getClientIP function
$userIP = filter_var(getClientIP(), FILTER_SANITIZE_STRING);
$deviceMAC = filter_var(getMacAddress(), FILTER_SANITIZE_STRING);

try {
    // Check if $userIP is not blank
    if (!empty($FindRollNo)) {
        // Use a prepared statement to prevent SQL injection
        $query = "INSERT INTO dts (search_value, IP, macaddress) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($CN, $query);

        // Check if the statement was prepared successfully
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $FindRollNo, $userIP, $deviceMAC);

            if (mysqli_stmt_execute($stmt)) {
                // Record inserted successfully
                $response = array('status' => 'success', 'message' => 'Record inserted successfully');
            } else {
                // Record insertion failed
                $response = array('status' => 'error', 'message' => 'Failed to insert record: ' . mysqli_error($CN));
            }

            // Close the statement
            mysqli_stmt_close($stmt);
        } else {
            // Statement preparation failed
            $response = array('status' => 'error', 'message' => 'Failed to prepare statement: ' . mysqli_error($CN));
        }
    } else {
        // $userIP is blank, so don't insert into the database
        $response = array('status' => 'error', 'message' => 'User IP is blank');
    }
} catch (Exception $ex) {
    $response = array('status' => 'error', 'message' => 'An error occurred: ' . $ex->getMessage());
}

// Close the first database connection
mysqli_close($CN);

// Second database connection
$CN2 = mysqli_connect("192.168.10.254", "tasviewer", "taspass");

if (!$CN2) {
    $response = array('status' => 'error', 'message' => 'Failed to connect to the database (tasdb): ' . mysqli_connect_error());
    echo json_encode($response);
    exit;
}

$DB2 = mysqli_select_db($CN2, "tasdb");


// Select the database (tasdb)
if (!$DB2) {
    $response = array('status' => 'error', 'message' => 'Failed to select the database (tasdb): ' . mysqli_error($CN2));
    echo json_encode($response);
    mysqli_close($CN2);
    exit;
}

// Prepare the SQL query with a placeholder for the input value
$query2 = "SELECT BarCodeNo, OfficeFrom, OfficeTo, DateIn, DateOut, Remarks FROM tas_inout WHERE BarCodeNo = ?";

// Use a prepared statement to prevent SQL injection
$stmt2 = mysqli_prepare($CN2, $query2);

if (!$stmt2) {
    $response = array('status' => 'error', 'message' => 'Failed to prepare statement: ' . mysqli_error($CN2));
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($stmt2, "s", $FindRollNo);
$result = mysqli_stmt_execute($stmt2);

if (!$result) {
    $response = array('status' => 'error', 'message' => 'Failed to execute query: ' . mysqli_stmt_error($stmt2));
    echo json_encode($response);
    exit;
}

// Bind the result variables
mysqli_stmt_bind_result($stmt2, $BarCodeNo, $OfficeFrom, $OfficeTo, $DateIn, $DateOut, $Remarks);

// Fetch the data and store it in an array
$response = array();
while (mysqli_stmt_fetch($stmt2)) {
    $response[] = array(
        "BarCodeNo" => $BarCodeNo,
        "OfficeFrom" => $OfficeFrom,
        "OfficeTo" => $OfficeTo,
        "DateIn" => $DateIn,
        "DateOut" => $DateOut,
        "Remarks" => $Remarks
    );
}

// Close the prepared statement and the second database connection
mysqli_stmt_close($stmt2);
mysqli_close($CN2);

// Output the JSON response
echo json_encode($response);

function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    return $ipaddress;
}
function getMacAddress() {
    $macAddress = '';

    // Check the operating system
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        exec('ipconfig /all', $output);
        foreach ($output as $line) {
            if (preg_match('/Physical[^:]+: (.*)/', $line, $matches)) {
                $macAddress = $matches[1];
                break;
            }
        }
    } else {
        // Unix-based system
        exec('ifconfig', $output);
        foreach ($output as $line) {
            if (preg_match('/HWaddr\s(\S+)/', $line, $matches)) {
                $macAddress = $matches[1];
                break;
            }
        }
    }

    return $macAddress;
  }