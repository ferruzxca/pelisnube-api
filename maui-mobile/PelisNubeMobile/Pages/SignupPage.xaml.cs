using PelisNubeMobile.Models;
using PelisNubeMobile.Services;

namespace PelisNubeMobile.Pages;

public partial class SignupPage : ContentPage
{
    private readonly ApiService _api;
    private readonly SessionService _session;

    public SignupPage(ApiService api, SessionService session)
    {
        InitializeComponent();
        _api = api;
        _session = session;

        PlanPicker.ItemsSource = new List<string> { "BASIC", "STANDARD", "PREMIUM" };
        PlanPicker.SelectedIndex = 1;
    }

    private async void OnSignupClicked(object? sender, EventArgs e)
    {
        SignupWithPaymentRequest payload = new()
        {
            Name = NameEntry.Text?.Trim() ?? string.Empty,
            Email = EmailEntry.Text?.Trim() ?? string.Empty,
            Password = PasswordEntry.Text ?? string.Empty,
            PlanCode = PlanPicker.SelectedItem?.ToString() ?? "STANDARD",
            CardNumber = CardNumberEntry.Text?.Trim() ?? string.Empty,
            CardBrand = string.IsNullOrWhiteSpace(CardBrandEntry.Text) ? "SIMULATED" : CardBrandEntry.Text.Trim(),
            PreferredLang = "es",
        };

        if (string.IsNullOrWhiteSpace(payload.Name) || string.IsNullOrWhiteSpace(payload.Email) ||
            string.IsNullOrWhiteSpace(payload.Password) || string.IsNullOrWhiteSpace(payload.CardNumber))
        {
            ResultLabel.Text = "Completa todos los campos obligatorios.";
            return;
        }

        SetBusy(true);
        ApiResult<SignupResponseData> result = await _api.SignupWithPaymentAsync(payload);
        SetBusy(false);

        if (!result.Success || result.Data is null || string.IsNullOrWhiteSpace(result.Data.Token))
        {
            ResultLabel.Text = $"{result.Message}\n{result.RawSnippet}";
            return;
        }

        await _session.SaveTokenAsync(result.Data.Token);
        await DisplayAlertAsync("Listo", "Cuenta creada correctamente.", "OK");
        if (Application.Current?.Windows.Count > 0)
        {
            Application.Current.Windows[0].Page = App.Services.GetRequiredService<MainTabsPage>();
        }
    }

    private void SetBusy(bool isBusy)
    {
        BusyIndicator.IsVisible = isBusy;
        BusyIndicator.IsRunning = isBusy;
        SignupButton.IsEnabled = !isBusy;
    }
}
