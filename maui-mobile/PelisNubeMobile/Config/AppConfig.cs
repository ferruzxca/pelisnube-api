namespace PelisNubeMobile.Config;

public static class AppConfig
{
    public static readonly string[] ApiBaseCandidates =
    {
        "https://peliapi.ferruzca.pro/api/v1",
        "https://peliapi.ferruzca.pro/index.php?route=/api/v1",
        "https://peliapi.ferruzca.pro/index.php?r=/api/v1"
    };

    public const string DefaultLanguage = "es";
}
