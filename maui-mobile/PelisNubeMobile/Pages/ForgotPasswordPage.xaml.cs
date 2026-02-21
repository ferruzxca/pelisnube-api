using Microsoft.Maui.Dispatching;
using PelisNubeMobile.Models;
using PelisNubeMobile.Services;

namespace PelisNubeMobile.Pages;

public partial class ForgotPasswordPage : ContentPage
{
    private readonly ApiService _api;
    private string? _resetToken;
    private bool _otpRequested;
    private bool _otpVerified;
    private bool _isBusy;
    private string _requestedEmail = string.Empty;
    private DateTimeOffset _otpCooldownUntil = DateTimeOffset.MinValue;
    private IDispatcherTimer? _cooldownTimer;

    public ForgotPasswordPage(ApiService api)
    {
        InitializeComponent();
        _api = api;
        ConfigureCooldownTimer();
        UpdateUiState();
    }

    private void ConfigureCooldownTimer()
    {
        _cooldownTimer = Dispatcher.CreateTimer();
        _cooldownTimer.Interval = TimeSpan.FromSeconds(1);
        _cooldownTimer.Tick += (_, _) => UpdateUiState();
    }

    private void OnEmailChanged(object? sender, TextChangedEventArgs e)
    {
        string currentEmail = NormalizeEmail(EmailEntry.Text);
        if (_requestedEmail != string.Empty
            && !string.Equals(currentEmail, _requestedEmail, StringComparison.OrdinalIgnoreCase))
        {
            _otpRequested = false;
            _otpVerified = false;
            _resetToken = null;
            CodeEntry.Text = string.Empty;
            NewPasswordEntry.Text = string.Empty;
            ResultLabel.Text = "Correo cambiado. Solicita un OTP nuevo.";
        }

        UpdateUiState();
    }

    private async void OnRequestOtpClicked(object? sender, EventArgs e)
    {
        if (_isBusy)
        {
            return;
        }

        string email = NormalizeEmail(EmailEntry.Text);
        if (string.IsNullOrWhiteSpace(email))
        {
            ResultLabel.Text = "Ingresa el correo.";
            return;
        }

        SetBusy(true);
        ApiResult<System.Text.Json.JsonElement> result = await _api.RequestPasswordOtpAsync(email);
        SetBusy(false);

        if (!result.Success)
        {
            ResultLabel.Text = $"{result.Message}\n{result.RawSnippet}";
            return;
        }

        _requestedEmail = email;
        _otpRequested = true;
        _otpVerified = false;
        _resetToken = null;
        _otpCooldownUntil = DateTimeOffset.UtcNow.AddSeconds(60);
        CodeEntry.Text = string.Empty;
        NewPasswordEntry.Text = string.Empty;
        ResultLabel.Text = "OTP enviado. Revisa tu correo (incluyendo spam).";
        UpdateUiState();
    }

    private async void OnVerifyOtpClicked(object? sender, EventArgs e)
    {
        if (_isBusy)
        {
            return;
        }

        if (!_otpRequested)
        {
            ResultLabel.Text = "Primero solicita el OTP.";
            return;
        }

        string email = NormalizeEmail(EmailEntry.Text);
        string code = CodeEntry.Text?.Trim() ?? string.Empty;

        if (string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(code))
        {
            ResultLabel.Text = "Correo y código son obligatorios.";
            return;
        }

        SetBusy(true);
        ApiResult<OtpVerifyData> result = await _api.VerifyPasswordOtpAsync(email, code);
        SetBusy(false);

        if (!result.Success || result.Data is null || string.IsNullOrWhiteSpace(result.Data.ResetToken))
        {
            ResultLabel.Text = $"{result.Message}\n{result.RawSnippet}";
            return;
        }

        _otpVerified = true;
        _resetToken = result.Data.ResetToken;
        ResultLabel.Text = "OTP verificado. Ya puedes cambiar contraseña.";
        UpdateUiState();
    }

    private async void OnResetPasswordClicked(object? sender, EventArgs e)
    {
        if (_isBusy)
        {
            return;
        }

        if (!_otpVerified || string.IsNullOrWhiteSpace(_resetToken))
        {
            ResultLabel.Text = "Primero verifica el OTP.";
            return;
        }

        string newPassword = NewPasswordEntry.Text ?? string.Empty;
        if (newPassword.Length < 8)
        {
            ResultLabel.Text = "La nueva contraseña debe tener al menos 8 caracteres.";
            return;
        }

        SetBusy(true);
        ApiResult<System.Text.Json.JsonElement> result = await _api.ResetPasswordAsync(_resetToken, newPassword);
        SetBusy(false);

        if (!result.Success)
        {
            ResultLabel.Text = $"{result.Message}\n{result.RawSnippet}";
            return;
        }

        await DisplayAlertAsync("Listo", "Contraseña actualizada.", "OK");
        await Navigation.PopAsync();
    }

    private void SetBusy(bool isBusy)
    {
        _isBusy = isBusy;
        BusyIndicator.IsVisible = isBusy;
        BusyIndicator.IsRunning = isBusy;
        UpdateUiState();
    }

    private void UpdateUiState()
    {
        bool hasEmail = !string.IsNullOrWhiteSpace(NormalizeEmail(EmailEntry.Text));
        bool inCooldown = DateTimeOffset.UtcNow < _otpCooldownUntil;

        if (_cooldownTimer is not null)
        {
            if (inCooldown && !_cooldownTimer.IsRunning)
            {
                _cooldownTimer.Start();
            }
            else if (!inCooldown && _cooldownTimer.IsRunning)
            {
                _cooldownTimer.Stop();
            }
        }

        EmailEntry.IsEnabled = !_isBusy && !_otpVerified;
        CodeEntry.IsEnabled = !_isBusy && _otpRequested;
        NewPasswordEntry.IsEnabled = !_isBusy && _otpVerified;

        RequestOtpButton.IsEnabled = !_isBusy && hasEmail && !inCooldown;
        VerifyOtpButton.IsEnabled = !_isBusy && _otpRequested && hasEmail;
        ResetPasswordButton.IsEnabled = !_isBusy && _otpVerified;

        if (_isBusy)
        {
            RequestOtpButton.Text = "Enviando...";
            return;
        }

        if (inCooldown)
        {
            int seconds = (int) Math.Ceiling((_otpCooldownUntil - DateTimeOffset.UtcNow).TotalSeconds);
            seconds = Math.Max(1, seconds);
            RequestOtpButton.Text = $"1) Reenviar OTP ({seconds}s)";
        }
        else
        {
            RequestOtpButton.Text = _otpRequested ? "1) Reenviar OTP" : "1) Solicitar OTP";
        }
    }

    private static string NormalizeEmail(string? email)
    {
        return (email ?? string.Empty).Trim().ToLowerInvariant();
    }

    protected override void OnDisappearing()
    {
        if (_cooldownTimer is not null && _cooldownTimer.IsRunning)
        {
            _cooldownTimer.Stop();
        }

        base.OnDisappearing();
    }
}
