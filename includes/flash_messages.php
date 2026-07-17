<?php
/**
 * Messages flash session — partagé entre init.php, PharmaPro ERP et le reste de l'app.
 */

if (!function_exists('redirectWithMessage')) {
    function redirectWithMessage($url, $message, $type = 'info')
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('displayFlashMessages')) {
    function displayFlashMessages()
    {
        if (!isset($_SESSION['flash_message'])) {
            return '';
        }

        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';

        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        $alertClass = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
        ];

        $iconClass = [
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle',
        ];

        $class = $alertClass[$type] ?? 'alert-info';
        $icon = $iconClass[$type] ?? 'fa-info-circle';

        return "<div class='alert $class alert-dismissible fade show' role='alert'>
                    <i class='fas $icon me-2'></i>
                    $message
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
}
