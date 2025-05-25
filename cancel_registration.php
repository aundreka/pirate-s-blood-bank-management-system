<?php
include('includes/db.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Check if this is a POST request with the required data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_registration']) && isset($_POST['event_id'])) {
    $eventId = intval($_POST['event_id']);
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if user is actually registered for this event
        $checkQuery = "SELECT registration_id FROM event_registrations WHERE event_id = ? AND user_id = ? AND status = 'registered'";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ii", $eventId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update registration status to cancelled
            $cancelQuery = "UPDATE event_registrations SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status = 'registered'";
            $cancelStmt = $conn->prepare($cancelQuery);
            $cancelStmt->bind_param("ii", $eventId, $userId);
            
            if ($cancelStmt->execute()) {
                // Decrease booked slots count
                $updateSlotsQuery = "UPDATE events SET booked_slots = booked_slots - 1 WHERE event_id = ? AND booked_slots > 0";
                $updateSlotsStmt = $conn->prepare($updateSlotsQuery);
                $updateSlotsStmt->bind_param("i", $eventId);
                $updateSlotsStmt->execute();
                $updateSlotsStmt->close();
                
                // Commit transaction
                $conn->commit();
                
                $response['success'] = true;
                $response['message'] = 'Your registration has been successfully cancelled.';
                
                // Set session variable for popup message
                $_SESSION['cancellation_message'] = 'Registration cancelled successfully!';
                
            } else {
                $conn->rollback();
                $response['message'] = 'Failed to cancel registration. Please try again.';
            }
            
            $cancelStmt->close();
        } else {
            $response['message'] = 'Registration not found or already cancelled.';
        }
        
        $checkStmt->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error cancelling registration: " . $e->getMessage());
        $response['message'] = 'An error occurred while cancelling your registration.';
    }
} else {
    $response['message'] = 'Invalid request.';
}

// Redirect back to the registration page with status
if (isset($_POST['event_id'])) {
    $eventId = intval($_POST['event_id']);
    if ($response['success']) {
        header("Location: register.php?event_id=$eventId&cancelled=1");
    } else {
        header("Location: register.php?event_id=$eventId&error=" . urlencode($response['message']));
    }
} else {
    header("Location: community.php#events");
}
exit();
?>