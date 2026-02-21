using PelisNubeMobile.Models;
using PelisNubeMobile.Services;

namespace PelisNubeMobile.Pages;

public partial class ProfilePage : ContentPage
{
    private readonly ApiService _api;
    private readonly SessionService _session;

    public ProfilePage(ApiService api, SessionService session)
    {
        InitializeComponent();
        _api = api;
        _session = session;
    }

    protected override async void OnAppearing()
    {
        base.OnAppearing();
        await LoadAsync();
    }

    private async Task LoadAsync()
    {
        await _session.InitializeAsync();
        if (string.IsNullOrWhiteSpace(_session.Token))
        {
            ErrorLabel.Text = "No hay sesión activa.";
            return;
        }

        SetBusy(true);
        ApiResult<UserProfile> meResult = await _api.GetMeAsync(_session.Token);
        ApiResult<UserSubscription> subResult = await _api.GetMySubscriptionAsync(_session.Token);
        SetBusy(false);

        if (!meResult.Success || meResult.Data is null)
        {
            ErrorLabel.Text = $"{meResult.Message}\n{meResult.RawSnippet}";
            return;
        }

        UserProfile me = meResult.Data;

        NameLabel.Text = me.Name;
        EmailLabel.Text = me.Email;
        RoleStatusLabel.Text = $"Rol: {me.Role} · Estado: {me.Status} · Activo: {(me.IsActive ? "Sí" : "No")}";
        LangLabel.Text = $"Idioma preferido: {me.PreferredLang}";

        if (subResult.Success && subResult.Data is not null)
        {
            UserSubscription sub = subResult.Data;
            string planName = sub.Plan?.Name ?? "-";
            string renewal = sub.RenewalAt ?? "-";
            SubscriptionLabel.Text = $"Plan: {planName} ({sub.Plan?.Code})\nEstado: {sub.Status}\nRenovación: {renewal}";
        }
        else
        {
            SubscriptionLabel.Text = "Sin datos de suscripción.";
        }
    }

    private async void OnRefreshClicked(object? sender, EventArgs e)
    {
        await LoadAsync();
    }

    private async void OnLogoutClicked(object? sender, EventArgs e)
    {
        await _session.ClearTokenAsync();
        if (Application.Current?.Windows.Count > 0)
        {
            Application.Current.Windows[0].Page = new NavigationPage(App.Services.GetRequiredService<LoginPage>());
        }
    }

    private void SetBusy(bool isBusy)
    {
        BusyIndicator.IsVisible = isBusy;
        BusyIndicator.IsRunning = isBusy;
    }
}
