using System.Text.Json.Serialization;

namespace PelisNubeMobile.Models;

public sealed class LoginResponseData
{
    [JsonPropertyName("token")]
    public string Token { get; set; } = string.Empty;

    [JsonPropertyName("user")]
    public UserProfile User { get; set; } = new();
}

public sealed class SignupWithPaymentRequest
{
    [JsonPropertyName("name")]
    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("email")]
    public string Email { get; set; } = string.Empty;

    [JsonPropertyName("password")]
    public string Password { get; set; } = string.Empty;

    [JsonPropertyName("planCode")]
    public string PlanCode { get; set; } = "STANDARD";

    [JsonPropertyName("cardNumber")]
    public string CardNumber { get; set; } = string.Empty;

    [JsonPropertyName("cardBrand")]
    public string CardBrand { get; set; } = "SIMULATED";

    [JsonPropertyName("preferredLang")]
    public string PreferredLang { get; set; } = "es";
}

public sealed class SignupResponseData
{
    [JsonPropertyName("token")]
    public string Token { get; set; } = string.Empty;

    [JsonPropertyName("user")]
    public UserProfile User { get; set; } = new();

    [JsonPropertyName("subscription")]
    public UserSubscription? Subscription { get; set; }
}

public sealed class OtpVerifyData
{
    [JsonPropertyName("resetToken")]
    public string ResetToken { get; set; } = string.Empty;
}
