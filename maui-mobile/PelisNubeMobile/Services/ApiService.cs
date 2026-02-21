using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using PelisNubeMobile.Config;
using PelisNubeMobile.Models;

namespace PelisNubeMobile.Services;

public sealed class ApiService
{
    private static readonly JsonSerializerOptions JsonOptions = new(JsonSerializerDefaults.Web)
    {
        PropertyNameCaseInsensitive = true,
    };

    private readonly HttpClient _httpClient;
    private string? _lastWorkingBase;

    public ApiService(HttpClient httpClient)
    {
        _httpClient = httpClient;
        _httpClient.Timeout = TimeSpan.FromSeconds(30);
    }

    public Task<ApiResult<LoginResponseData>> LoginAsync(string email, string password) =>
        SendAsync<LoginResponseData>(HttpMethod.Post, "auth/login", new
        {
            email,
            password,
        });

    public Task<ApiResult<SignupResponseData>> SignupWithPaymentAsync(SignupWithPaymentRequest request) =>
        SendAsync<SignupResponseData>(HttpMethod.Post, "auth/signup-with-payment", request);

    public Task<ApiResult<JsonElement>> RequestPasswordOtpAsync(string email) =>
        SendAsync<JsonElement>(HttpMethod.Post, "auth/password/otp/request", new
        {
            email,
        });

    public Task<ApiResult<OtpVerifyData>> VerifyPasswordOtpAsync(string email, string code) =>
        SendAsync<OtpVerifyData>(HttpMethod.Post, "auth/password/otp/verify", new
        {
            email,
            code,
        });

    public Task<ApiResult<JsonElement>> ResetPasswordAsync(string token, string newPassword) =>
        SendAsync<JsonElement>(HttpMethod.Post, "auth/password/reset", new
        {
            token,
            newPassword,
        });

    public Task<ApiResult<PagedData<ContentItem>>> GetCatalogAsync(int page = 1, int pageSize = 20) =>
        SendAsync<PagedData<ContentItem>>(HttpMethod.Get, $"catalog?page={page}&pageSize={pageSize}");

    public Task<ApiResult<ContentItem>> GetCatalogDetailAsync(string slug, string token) =>
        SendAsync<ContentItem>(HttpMethod.Get, $"catalog/{Uri.EscapeDataString(slug)}", token: token);

    public Task<ApiResult<ItemsResponse<SectionItem>>> GetSectionsHomeAsync(string token) =>
        SendAsync<ItemsResponse<SectionItem>>(HttpMethod.Get, "sections/home", token: token);

    public Task<ApiResult<UserProfile>> GetMeAsync(string token) =>
        SendAsync<UserProfile>(HttpMethod.Get, "auth/me", token: token);

    public Task<ApiResult<UserSubscription>> GetMySubscriptionAsync(string token) =>
        SendAsync<UserSubscription>(HttpMethod.Get, "subscription/me", token: token);

    private async Task<ApiResult<T>> SendAsync<T>(HttpMethod method, string relativePath, object? body = null, string? token = null)
    {
        List<string> candidates = BuildCandidateUrls(relativePath);
        ApiResult<T>? fallbackError = null;

        foreach (string url in candidates)
        {
            using HttpRequestMessage request = new(method, url);
            request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
            request.Headers.AcceptLanguage.Add(new StringWithQualityHeaderValue(AppConfig.DefaultLanguage));

            if (!string.IsNullOrWhiteSpace(token))
            {
                request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
            }

            if (body is not null)
            {
                string payload = JsonSerializer.Serialize(body, JsonOptions);
                request.Content = new StringContent(payload, Encoding.UTF8, "application/json");
            }

            HttpResponseMessage response;
            string raw;
            try
            {
                response = await _httpClient.SendAsync(request);
                raw = await response.Content.ReadAsStringAsync();
            }
            catch (Exception ex)
            {
                fallbackError = ApiResult<T>.Fail($"Error de red: {ex.Message}", 0, url, null);
                continue;
            }

            ApiEnvelope<JsonElement>? envelope;
            try
            {
                envelope = JsonSerializer.Deserialize<ApiEnvelope<JsonElement>>(raw, JsonOptions);
            }
            catch
            {
                envelope = null;
            }

            if (envelope is null)
            {
                fallbackError = ApiResult<T>.Fail(
                    $"Respuesta no JSON (HTTP {(int)response.StatusCode}).",
                    (int)response.StatusCode,
                    url,
                    TrimSnippet(raw));
                continue;
            }

            if (!envelope.Success)
            {
                return ApiResult<T>.Fail(
                    envelope.Message,
                    (int)response.StatusCode,
                    url,
                    envelope.Errors.ValueKind != JsonValueKind.Undefined ? envelope.Errors.ToString() : TrimSnippet(raw));
            }

            T? data = default;
            if (typeof(T) == typeof(JsonElement))
            {
                data = (T)(object)envelope.Data;
            }
            else if (envelope.Data.ValueKind != JsonValueKind.Undefined && envelope.Data.ValueKind != JsonValueKind.Null)
            {
                data = envelope.Data.Deserialize<T>(JsonOptions);
            }

            _lastWorkingBase = ExtractBaseFromResolvedUrl(url);
            return ApiResult<T>.Ok(data, envelope.Message, (int)response.StatusCode, url);
        }

        return fallbackError ?? ApiResult<T>.Fail("No fue posible conectar con la API.", 0, null, null);
    }

    private List<string> BuildCandidateUrls(string relativePath)
    {
        string normalizedPath = relativePath.TrimStart('/');
        List<string> baseCandidates = new();

        if (!string.IsNullOrWhiteSpace(_lastWorkingBase))
        {
            baseCandidates.Add(_lastWorkingBase);
        }

        foreach (string candidate in AppConfig.ApiBaseCandidates)
        {
            if (!baseCandidates.Contains(candidate, StringComparer.OrdinalIgnoreCase))
            {
                baseCandidates.Add(candidate);
            }
        }

        List<string> urls = new();
        foreach (string baseUrl in baseCandidates)
        {
            foreach (string u in BuildUrlsForBase(baseUrl, normalizedPath))
            {
                if (!urls.Contains(u, StringComparer.OrdinalIgnoreCase))
                {
                    urls.Add(u);
                }
            }
        }

        return urls;
    }

    private static IEnumerable<string> BuildUrlsForBase(string baseUrl, string relativePath)
    {
        string safeBase = baseUrl.Trim();
        if (string.IsNullOrWhiteSpace(safeBase))
        {
            yield break;
        }

        if (Uri.TryCreate(safeBase, UriKind.Absolute, out Uri? uri) && !string.IsNullOrWhiteSpace(uri.Query))
        {
            Dictionary<string, string> query = ParseQuery(uri.Query);
            if (query.TryGetValue("route", out string? routeValue) || query.TryGetValue("r", out routeValue))
            {
                string route = string.IsNullOrWhiteSpace(routeValue) ? "/api/v1" : "/" + routeValue.Trim('/');
                if (!route.Contains("/api/v", StringComparison.OrdinalIgnoreCase))
                {
                    route = route.TrimEnd('/') + "/api/v1";
                }

                string finalRoute = route.TrimEnd('/') + "/" + relativePath;
                if (query.ContainsKey("route"))
                {
                    query["route"] = finalRoute;
                }
                else
                {
                    query["r"] = finalRoute;
                }

                yield return BuildUriWithQuery(uri, query);
                yield break;
            }
        }

        string cleaned = safeBase.TrimEnd('/');
        if (cleaned.Contains("/api/v", StringComparison.OrdinalIgnoreCase))
        {
            yield return $"{cleaned}/{relativePath}";
        }
        else
        {
            yield return $"{cleaned}/api/v1/{relativePath}";
            yield return $"{cleaned}/index.php?route={Uri.EscapeDataString("/api/v1/" + relativePath)}";
            yield return $"{cleaned}/index.php?r={Uri.EscapeDataString("/api/v1/" + relativePath)}";
        }
    }

    private static Dictionary<string, string> ParseQuery(string query)
    {
        Dictionary<string, string> parsed = new(StringComparer.OrdinalIgnoreCase);
        string raw = query.TrimStart('?');
        if (string.IsNullOrWhiteSpace(raw))
        {
            return parsed;
        }

        foreach (string pair in raw.Split('&', StringSplitOptions.RemoveEmptyEntries))
        {
            string[] parts = pair.Split('=', 2);
            string key = Uri.UnescapeDataString(parts[0]);
            string value = parts.Length > 1 ? Uri.UnescapeDataString(parts[1]) : string.Empty;
            parsed[key] = value;
        }

        return parsed;
    }

    private static string BuildUriWithQuery(Uri uri, Dictionary<string, string> query)
    {
        string queryString = string.Join("&", query.Select(kv =>
            $"{Uri.EscapeDataString(kv.Key)}={Uri.EscapeDataString(kv.Value)}"));

        UriBuilder builder = new(uri)
        {
            Query = queryString,
        };

        return builder.Uri.ToString();
    }

    private static string TrimSnippet(string? raw)
    {
        if (string.IsNullOrWhiteSpace(raw))
        {
            return string.Empty;
        }

        string compact = raw.Replace("\n", " ").Replace("\r", " ").Trim();
        return compact.Length <= 220 ? compact : compact[..220];
    }

    private static string ExtractBaseFromResolvedUrl(string resolvedUrl)
    {
        if (!Uri.TryCreate(resolvedUrl, UriKind.Absolute, out Uri? uri))
        {
            return resolvedUrl;
        }

        string path = uri.AbsolutePath;
        if (path.Contains("/api/v", StringComparison.OrdinalIgnoreCase))
        {
            int apiIndex = path.IndexOf("/api/v", StringComparison.OrdinalIgnoreCase);
            return uri.GetLeftPart(UriPartial.Authority) + path[..(apiIndex + 7)];
        }

        return uri.GetLeftPart(UriPartial.Path).TrimEnd('/');
    }
}
