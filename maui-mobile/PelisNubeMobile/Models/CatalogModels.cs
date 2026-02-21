using System.Text.Json.Serialization;

namespace PelisNubeMobile.Models;

public sealed class ContentItem
{
    private const string RepeatedSeedPosterId = "photo-1536440136628-849c177e76a1";
    private const string RepeatedSeedBannerId = "photo-1524985069026-dd778a71c7b4";

    [JsonPropertyName("id")]
    public string Id { get; set; } = string.Empty;

    [JsonPropertyName("title")]
    public string Title { get; set; } = string.Empty;

    [JsonPropertyName("slug")]
    public string Slug { get; set; } = string.Empty;

    [JsonPropertyName("type")]
    public string Type { get; set; } = string.Empty;

    [JsonPropertyName("synopsis")]
    public string Synopsis { get; set; } = string.Empty;

    [JsonPropertyName("year")]
    public int Year { get; set; }

    [JsonPropertyName("duration")]
    public int Duration { get; set; }

    [JsonPropertyName("rating")]
    public double Rating { get; set; }

    [JsonPropertyName("trailerWatchUrl")]
    public string TrailerWatchUrl { get; set; } = string.Empty;

    [JsonPropertyName("trailerEmbedUrl")]
    public string? TrailerEmbedUrl { get; set; }

    [JsonPropertyName("posterUrl")]
    public string PosterUrl { get; set; } = string.Empty;

    [JsonPropertyName("bannerUrl")]
    public string BannerUrl { get; set; } = string.Empty;

    [JsonIgnore]
    public string PosterDisplayUrl => ResolveDisplayImageUrl(PosterUrl, Title, true);

    [JsonIgnore]
    public string BannerDisplayUrl => ResolveDisplayImageUrl(BannerUrl, Title, false);

    private static string ResolveDisplayImageUrl(string rawUrl, string title, bool isPoster)
    {
        if (IsLikelyRepeatedSeedUrl(rawUrl, isPoster))
        {
            return BuildTitlePlaceholderUrl(title, isPoster);
        }

        if (Uri.TryCreate(rawUrl, UriKind.Absolute, out _))
        {
            return rawUrl;
        }

        return BuildTitlePlaceholderUrl(title, isPoster);
    }

    private static bool IsLikelyRepeatedSeedUrl(string url, bool isPoster)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out Uri? uri))
        {
            return true;
        }

        if (!uri.Host.Contains("images.unsplash.com", StringComparison.OrdinalIgnoreCase))
        {
            return false;
        }

        return isPoster
            ? uri.AbsolutePath.Contains(RepeatedSeedPosterId, StringComparison.OrdinalIgnoreCase)
            : uri.AbsolutePath.Contains(RepeatedSeedBannerId, StringComparison.OrdinalIgnoreCase);
    }

    private static string BuildTitlePlaceholderUrl(string title, bool isPoster)
    {
        string cleanTitle = string.IsNullOrWhiteSpace(title) ? "PELICULA" : title.Trim();
        string text = Uri.EscapeDataString(cleanTitle.ToUpperInvariant()).Replace("%20", "+");

        if (isPoster)
        {
            return $"https://dummyimage.com/800x1200/111827/f8fafc.png&text={text}";
        }

        return $"https://dummyimage.com/1600x900/1e1b4b/fbcfe8.png&text={text}";
    }
}

public sealed class SectionItem
{
    [JsonPropertyName("id")]
    public string Id { get; set; } = string.Empty;

    [JsonPropertyName("key")]
    public string Key { get; set; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("description")]
    public string Description { get; set; } = string.Empty;

    [JsonPropertyName("items")]
    public List<ContentItem> Items { get; set; } = new();
}
