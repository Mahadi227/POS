<?php
// includes/Middleware/AuthMiddleware.php

require_once __DIR__ . '/../Config/session.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Auth/PermissionService.php';

class AuthMiddleware {

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        if (!isset($_SESSION['user_id'])) {
            self::redirect('/public/login.php');
            exit;
        }

        // Check timeout
        if (!check_session_timeout()) {
            self::redirect('/public/login.php?error=timeout');
            exit;
        }
    }

    /**
     * Check if user has one of the allowed roles (slug or display name).
     * @param array $allowedRoles Array of role names or slugs
     */
    public static function hasRole($allowedRoles) {
        self::isAuthenticated();

        $userRole = self::roleSlug($_SESSION['role_slug'] ?? $_SESSION['role'] ?? '');
        $allowed = array_map([self::class, 'roleSlug'], $allowedRoles);

        if (!in_array($userRole, $allowed, true) && !PermissionService::isSuperAdmin()) {
            self::accessDeniedPublic();
        }
    }

    /**
     * Protect an API endpoint
     */
    public static function apiProtect($allowedRoles = []) {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in."]);
            exit;
        }

        if (!check_session_timeout()) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Session expired."]);
            exit;
        }

        if (!empty($allowedRoles)) {
            $userRole = self::roleSlug($_SESSION['role_slug'] ?? $_SESSION['role'] ?? '');
            $allowed = array_map([self::class, 'roleSlug'], $allowedRoles);

            if (!in_array($userRole, $allowed, true) && !PermissionService::isSuperAdmin()) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Forbidden. Insufficient permissions."]);
                exit;
            }
        }
    }

    public static function roleSlug(string $role): string
    {
        return RoleRedirect::slug($role);
    }

    public static function accessDeniedPublic(): void
    {
        self::accessDenied();
    }

    private static function redirect($url) {
        // Build base URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        
        // In this local setup, find the base path dynamically if needed, 
        // or just rely on relative/absolute root paths
        header("Location: $url");
        exit;
    }

    private static function accessDenied() {
        http_response_code(403);
        // Simple access denied page
        echo "<!DOCTYPE html>
              <html>
              <head>
                  <title>Access Denied</title>
                  <style>
                      body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; background: #f8fafc; color: #0f172a; }
                      .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; }
                      h1 { color: #ef4444; }
                  </style>
              </head>
              <body>
                  <div class='card'>
                      <h1>403 Forbidden</h1>
                      <p>You do not have permission to access this page.</p>
                      <br>
                      <a href='/public/login.php' style='color: #2563eb; text-decoration: none;'>Return to Login</a>
                  </div>
              </body>
              </html>";
        exit;
    }
}
