using PelisNubeMobile.Models;
using PelisNubeMobile.Services;

namespace PelisNubeMobile.Pages;

public partial class LoginPage : ContentPage
{
    private readonly ApiService _api;
    private readonly SessionService _session;
    private bool _checkedSession;

    public LoginPage(ApiService api, SessionService session)
    {
        InitializeComponent();
        _api = api;
        _session = session;

        EmailEntry.Text = "ferruzca@pelisnube.local";
        PasswordEntry.Text = "2812Admin";
    }

    protected override async void OnAppearing()
    {
        base.OnAppearing();

        if (_checkedSession)
        {
            return;
        }

        _checkedSession = true;
        await _session.InitializeAsync();

        if (!string.IsNullOrWhiteSpace(_session.Token))
        {
            ApiResult<UserProfile> me = await _api.GetMeAsync(_session.Token);
            if (me.Success)
            {
                GoToMain();
                return;
            }

            await _session.ClearTokenAsync();
        }
    }

    private async void OnLoginClicked(object? sender, EventArgs e)
    {
        string email = EmailEntry.Text?.Trim() ?? string.Empty;
        string password = PasswordEntry.Text ?? string.Empty;

        if (string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(password))
        {
            InfoLabel.Text = "Correo y contraseña son obligatorios.";
            return;
        }

        SetBusy(true);
        ApiResult<LoginResponseData> result = await _api.LoginAsync(email, password);
        SetBusy(false);

        if (!result.Success || result.Data is null || string.IsNullOrWhiteSpace(result.Data.Token))
        {
            InfoLabel.Text = $"{result.Message}\n{result.RawSnippet}";
            return;
        }

        await _session.SaveTokenAsync(result.Data.Token);
        GoToMain();
    }

    private async void OnSignupClicked(object? sender, EventArgs e)
    {
        await Navigation.PushAsync(App.Services.GetRequiredService<SignupPage>());
    }

    private async void OnForgotClicked(object? sender, EventArgs e)
    {
        await Navigation.PushAsync(App.Services.GetRequiredService<ForgotPasswordPage>());
    }

    private static void GoToMain()
    {
        if (Application.Current?.Windows.Count > 0)
        {
            Application.Current.Windows[0].Page = App.Services.GetRequiredService<MainTabsPage>();
        }
    }

    private void SetBusy(bool isBusy)
    {
        BusyIndicator.IsVisible = isBusy;
        BusyIndicator.IsRunning = isBusy;
        LoginButton.IsEnabled = !isBusy;
    }
}
