using Microsoft.Extensions.DependencyInjection;
using PelisNubeMobile.Pages;

namespace PelisNubeMobile;

public partial class App : Application
{
    public static IServiceProvider Services { get; private set; } = default!;

    public App(IServiceProvider services)
    {
        InitializeComponent();
        Services = services;
    }

    protected override Window CreateWindow(IActivationState? activationState)
    {
        Page login = Services.GetRequiredService<LoginPage>();
        return new Window(new NavigationPage(login));
    }
}
