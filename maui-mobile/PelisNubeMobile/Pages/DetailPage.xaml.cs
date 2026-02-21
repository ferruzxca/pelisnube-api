using PelisNubeMobile.Models;
using PelisNubeMobile.Services;

namespace PelisNubeMobile.Pages;

public partial class DetailPage : ContentPage
{
    private readonly ApiService _api;
    private readonly SessionService _session;
    private readonly string _slug;
    private string? _watchUrl;

    public DetailPage(ApiService api, SessionService session, string slug, string title)
    {
        InitializeComponent();
        _api = api;
        _session = session;
        _slug = slug;
        Title = string.IsNullOrWhiteSpace(title) ? "Detalle" : title;
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
            ErrorLabel.Text = "Token no disponible.";
            return;
        }

        SetBusy(true);
        ApiResult<ContentItem> result = await _api.GetCatalogDetailAsync(_slug, _session.Token);
        SetBusy(false);

        if (!result.Success || result.Data is null)
        {
            ErrorLabel.Text = $"{result.Message}\n{result.RawSnippet}";
            return;
        }

        ContentItem item = result.Data;
        TitleLabel.Text = item.Title;
        MetaLabel.Text = $"{item.Type} · {item.Year} · {item.Duration} min · ⭐ {item.Rating:0.0}";
        SynopsisLabel.Text = item.Synopsis;
        BannerImage.Source = item.BannerDisplayUrl;

        _watchUrl = string.IsNullOrWhiteSpace(item.TrailerWatchUrl) ? null : item.TrailerWatchUrl;
        string videoUrl = BuildPlayableTrailerUrl(item);

        if (string.IsNullOrWhiteSpace(videoUrl))
        {
            ErrorLabel.Text = "No hay trailer disponible.";
            return;
        }

        // Load the trailer URL directly in WebView (no nested iframe) to avoid YouTube error 153.
        TrailerWebView.Source = new UrlWebViewSource { Url = videoUrl };
    }

    private async void OnOpenTrailerClicked(object? sender, EventArgs e)
    {
        if (string.IsNullOrWhiteSpace(_watchUrl))
        {
            await DisplayAlertAsync("Trailer", "No hay URL de trailer.", "OK");
            return;
        }

        await Browser.OpenAsync(_watchUrl, BrowserLaunchMode.SystemPreferred);
    }

    private void SetBusy(bool isBusy)
    {
        BusyIndicator.IsVisible = isBusy;
        BusyIndicator.IsRunning = isBusy;
    }

    private static string BuildPlayableTrailerUrl(ContentItem item)
    {
        if (Uri.TryCreate(item.TrailerWatchUrl, UriKind.Absolute, out Uri? directWatch))
        {
            if (IsYouTubeHost(directWatch.Host))
            {
                return directWatch.ToString();
            }

            return directWatch.ToString();
        }

        if (Uri.TryCreate(item.TrailerEmbedUrl, UriKind.Absolute, out Uri? embedUri))
        {
            if (IsYouTubeHost(embedUri.Host))
            {
                string videoId = ExtractYouTubeVideoId(embedUri);
                return BuildYouTubeWatch(videoId);
            }

            return embedUri.ToString();
        }

        return string.Empty;
    }

    private static bool IsYouTubeHost(string host) =>
        host.Contains("youtube.com", StringComparison.OrdinalIgnoreCase) ||
        host.Contains("youtu.be", StringComparison.OrdinalIgnoreCase);

    private static string ExtractYouTubeVideoId(Uri uri)
    {
        string host = uri.Host.ToLowerInvariant();

        if (host.Contains("youtu.be"))
        {
            return uri.AbsolutePath.Trim('/').Split('/')[0];
        }

        if (host.Contains("youtube.com"))
        {
            if (uri.AbsolutePath.StartsWith("/embed/", StringComparison.OrdinalIgnoreCase))
            {
                return uri.AbsolutePath["/embed/".Length..].Split('/')[0];
            }

            Dictionary<string, string> query = ParseQuery(uri.Query);
            if (query.TryGetValue("v", out string? videoId))
            {
                return videoId;
            }
        }

        return string.Empty;
    }

    private static Dictionary<string, string> ParseQuery(string query)
    {
        Dictionary<string, string> result = new(StringComparer.OrdinalIgnoreCase);
        string raw = query.TrimStart('?');
        if (string.IsNullOrWhiteSpace(raw))
        {
            return result;
        }

        foreach (string part in raw.Split('&', StringSplitOptions.RemoveEmptyEntries))
        {
            string[] tokens = part.Split('=', 2);
            string key = Uri.UnescapeDataString(tokens[0]);
            string value = tokens.Length > 1 ? Uri.UnescapeDataString(tokens[1]) : string.Empty;
            result[key] = value;
        }

        return result;
    }

    private static string BuildYouTubeWatch(string videoId)
    {
        if (string.IsNullOrWhiteSpace(videoId))
        {
            return string.Empty;
        }

        string safeId = Uri.EscapeDataString(videoId);
        return $"https://www.youtube.com/watch?v={safeId}";
    }
}
