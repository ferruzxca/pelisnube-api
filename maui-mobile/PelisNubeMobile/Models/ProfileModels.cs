using System.Text.Json.Serialization;

namespace PelisNubeMobile.Models;

public sealed class UserProfile
{
    [JsonPropertyName("id")]
    public string Id { get; set; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("email")]
    public string Email { get; set; } = string.Empty;

    [JsonPropertyName("role")]
    public string Role { get; set; } = string.Empty;

    [JsonPropertyName("status")]
    public string Status { get; set; } = string.Empty;

    [JsonPropertyName("isActive")]
    public bool IsActive { get; set; }

    [JsonPropertyName("preferredLang")]
    public string PreferredLang { get; set; } = "es";

    [JsonPropertyName("mustChangePassword")]
    public bool MustChangePassword { get; set; }

    [JsonPropertyName("subscription")]
    public UserSubscription? Subscription { get; set; }
}

public sealed class UserSubscription
{
    [JsonPropertyName("id")]
    public string Id { get; set; } = string.Empty;

    [JsonPropertyName("status")]
    public string Status { get; set; } = string.Empty;

    [JsonPropertyName("startedAt")]
    public string? StartedAt { get; set; }

    [JsonPropertyName("renewalAt")]
    public string? RenewalAt { get; set; }

    [JsonPropertyName("endedAt")]
    public string? EndedAt { get; set; }

    [JsonPropertyName("isActive")]
    public bool IsActive { get; set; }

    [JsonPropertyName("plan")]
    public SubscriptionPlan? Plan { get; set; }
}

public sealed class SubscriptionPlan
{
    [JsonPropertyName("id")]
    public string Id { get; set; } = string.Empty;

    [JsonPropertyName("code")]
    public string Code { get; set; } = string.Empty;

    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("priceMonthly")]
    public double PriceMonthly { get; set; }

    [JsonPropertyName("currency")]
    public string Currency { get; set; } = "MXN";

    [JsonPropertyName("quality")]
    public string? Quality { get; set; }

    [JsonPropertyName("screens")]
    public int Screens { get; set; }
}
