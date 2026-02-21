namespace PelisNubeMobile.Services;

public sealed class SessionService
{
    private const string TokenKey = "pelisnube_token";

    public string? Token { get; private set; }

    public async Task InitializeAsync()
    {
        if (!string.IsNullOrWhiteSpace(Token))
        {
            return;
        }

        try
        {
            Token = await SecureStorage.Default.GetAsync(TokenKey);
        }
        catch
        {
            string stored = Preferences.Default.Get(TokenKey, string.Empty);
            Token = string.IsNullOrWhiteSpace(stored) ? null : stored;
        }
    }

    public async Task SaveTokenAsync(string token)
    {
        Token = token;
        try
        {
            await SecureStorage.Default.SetAsync(TokenKey, token);
        }
        catch
        {
            Preferences.Default.Set(TokenKey, token);
        }
    }

    public Task ClearTokenAsync()
    {
        Token = null;
        try
        {
            SecureStorage.Default.Remove(TokenKey);
        }
        catch
        {
            Preferences.Default.Remove(TokenKey);
        }

        return Task.CompletedTask;
    }
}
