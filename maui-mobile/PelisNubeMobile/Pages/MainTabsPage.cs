namespace PelisNubeMobile.Pages;

public sealed class MainTabsPage : TabbedPage
{
    public MainTabsPage(HomePage homePage, ProfilePage profilePage)
    {
        BarBackgroundColor = Color.FromArgb("#0F1226");
        BarTextColor = Colors.White;

        Children.Add(new NavigationPage(homePage)
        {
            Title = "Inicio",
            BarBackgroundColor = Color.FromArgb("#0F1226"),
            BarTextColor = Colors.White,
        });

        Children.Add(new NavigationPage(profilePage)
        {
            Title = "Perfil",
            BarBackgroundColor = Color.FromArgb("#0F1226"),
            BarTextColor = Colors.White,
        });
    }
}
