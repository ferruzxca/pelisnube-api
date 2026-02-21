<?php

declare(strict_types=1);

namespace App\Core;

final class I18n
{
    /** @var array<string, array<string, string>> */
    private static array $messages = [
        'es' => [
            'ok' => 'OK',
            'not_found' => 'Ruta no encontrada.',
            'unauthorized' => 'No autorizado.',
            'forbidden' => 'Sin permisos.',
            'validation_error' => 'Datos invalidos.',
            'server_error' => 'Error interno del servidor.',
            'catalog_list' => 'Catalogo obtenido correctamente.',
            'catalog_detail' => 'Detalle obtenido correctamente.',
            'sections_home' => 'Secciones de inicio obtenidas correctamente.',
            'login_success' => 'Sesion iniciada correctamente.',
            'me_success' => 'Perfil obtenido correctamente.',
            'signup_success' => 'Cuenta creada correctamente tras pago simulado exitoso.',
            'signup_failed_payment' => 'Pago simulado rechazado. La cuenta no fue creada.',
            'generic_otp_request' => 'Si el correo existe, enviaremos un codigo en breve.',
            'otp_verified' => 'Codigo verificado correctamente.',
            'password_reset_success' => 'Contrasena actualizada correctamente.',
            'favorites_list' => 'Favoritos obtenidos correctamente.',
            'favorite_saved' => 'Favorito guardado correctamente.',
            'favorite_inactive' => 'Favorito inactivado correctamente.',
            'subscription_me' => 'Suscripcion obtenida correctamente.',
            'subscription_changed' => 'Plan actualizado correctamente.',
            'subscription_canceled' => 'Suscripcion cancelada correctamente.',
            'admin_kpis' => 'KPIs obtenidos correctamente.',
            'admin_chart' => 'Datos de grafica obtenidos correctamente.',
            'admin_audit' => 'Auditoria obtenida correctamente.',
            'resource_list' => 'Listado obtenido correctamente.',
            'resource_created' => 'Registro creado correctamente.',
            'resource_updated' => 'Registro actualizado correctamente.',
            'resource_inactive' => 'Registro inactivado correctamente.',
        ],
        'en' => [
            'ok' => 'OK',
            'not_found' => 'Route not found.',
            'unauthorized' => 'Unauthorized.',
            'forbidden' => 'Forbidden.',
            'validation_error' => 'Invalid data.',
            'server_error' => 'Internal server error.',
            'catalog_list' => 'Catalog retrieved successfully.',
            'catalog_detail' => 'Catalog detail retrieved successfully.',
            'sections_home' => 'Home sections retrieved successfully.',
            'login_success' => 'Login successful.',
            'me_success' => 'Profile retrieved successfully.',
            'signup_success' => 'Account created successfully after simulated payment.',
            'signup_failed_payment' => 'Simulated payment failed. Account was not created.',
            'generic_otp_request' => 'If the email exists, we will send a code shortly.',
            'otp_verified' => 'OTP verified successfully.',
            'password_reset_success' => 'Password updated successfully.',
            'favorites_list' => 'Favorites retrieved successfully.',
            'favorite_saved' => 'Favorite saved successfully.',
            'favorite_inactive' => 'Favorite disabled successfully.',
            'subscription_me' => 'Subscription retrieved successfully.',
            'subscription_changed' => 'Subscription updated successfully.',
            'subscription_canceled' => 'Subscription canceled successfully.',
            'admin_kpis' => 'KPIs retrieved successfully.',
            'admin_chart' => 'Chart data retrieved successfully.',
            'admin_audit' => 'Audit logs retrieved successfully.',
            'resource_list' => 'List retrieved successfully.',
            'resource_created' => 'Record created successfully.',
            'resource_updated' => 'Record updated successfully.',
            'resource_inactive' => 'Record inactivated successfully.',
        ],
    ];

    public static function resolveLanguage(?string $acceptLanguage): string
    {
        if (!$acceptLanguage) {
            return (string) Env::get('DEFAULT_LANG', 'es');
        }

        $primary = strtolower(trim(explode(',', $acceptLanguage)[0] ?? 'es'));
        $lang = substr($primary, 0, 2);

        return array_key_exists($lang, self::$messages) ? $lang : (string) Env::get('DEFAULT_LANG', 'es');
    }

    public static function t(string $key, string $lang = 'es'): string
    {
        if (!isset(self::$messages[$lang])) {
            $lang = 'es';
        }

        return self::$messages[$lang][$key] ?? $key;
    }
}
