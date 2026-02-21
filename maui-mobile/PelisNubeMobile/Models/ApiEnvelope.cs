using System.Text.Json;
using System.Text.Json.Serialization;

namespace PelisNubeMobile.Models;

public sealed class ApiEnvelope<T>
{
    [JsonPropertyName("success")]
    public bool Success { get; set; }

    [JsonPropertyName("message")]
    public string Message { get; set; } = string.Empty;

    [JsonPropertyName("data")]
    public T? Data { get; set; }

    [JsonPropertyName("errors")]
    public JsonElement Errors { get; set; }
}

public sealed class ApiResult<T>
{
    public bool Success { get; init; }
    public int StatusCode { get; init; }
    public string Message { get; init; } = string.Empty;
    public T? Data { get; init; }
    public string? ResolvedUrl { get; init; }
    public string? RawSnippet { get; init; }

    public static ApiResult<T> Ok(T? data, string message, int statusCode, string? resolvedUrl) =>
        new()
        {
            Success = true,
            Data = data,
            Message = message,
            StatusCode = statusCode,
            ResolvedUrl = resolvedUrl,
        };

    public static ApiResult<T> Fail(string message, int statusCode, string? resolvedUrl = null, string? rawSnippet = null) =>
        new()
        {
            Success = false,
            Message = message,
            StatusCode = statusCode,
            ResolvedUrl = resolvedUrl,
            RawSnippet = rawSnippet,
        };
}

public sealed class ItemsResponse<T>
{
    [JsonPropertyName("items")]
    public List<T> Items { get; set; } = new();
}

public sealed class PagedData<T>
{
    [JsonPropertyName("items")]
    public List<T> Items { get; set; } = new();

    [JsonPropertyName("page")]
    public int Page { get; set; }

    [JsonPropertyName("pageSize")]
    public int PageSize { get; set; }

    [JsonPropertyName("total")]
    public int Total { get; set; }
}
