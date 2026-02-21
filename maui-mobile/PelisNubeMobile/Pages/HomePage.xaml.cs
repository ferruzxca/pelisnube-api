using System.Collections.ObjectModel;
using System.Windows.Input;
using PelisNubeMobile.Models;
using PelisNubeMobile.Services;

namespace PelisNubeMobile.Pages;

public partial class HomePage : ContentPage
{
    private readonly ApiService _api;
    private readonly SessionService _session;
    private bool _loaded;
    private bool _isLoading;

    public ObservableCollection<ContentItem> FeaturedItems { get; } = new();
    public ObservableCollection<SectionItem> Sections { get; } = new();

    public ICommand RefreshCommand { get; }

    public bool IsLoading
    {
        get => _isLoading;
        set
        {
            if (_isLoading != value)
            {
                _isLoading = value;
                OnPropertyChanged();
            }
        }
    }

    public HomePage(ApiService api, SessionService session)
    {
        InitializeComponent();
        _api = api;
        _session = session;
        RefreshCommand = new Command(async () => await LoadAsync(force: true));
        BindingContext = this;
    }

    protected override async void OnAppearing()
    {
        base.OnAppearing();

        if (_loaded)
        {
            return;
        }

        _loaded = true;
        await LoadAsync(force: false);
    }

    private async Task LoadAsync(bool force)
    {
        if (IsLoading)
        {
            return;
        }

        await _session.InitializeAsync();
        if (string.IsNullOrWhiteSpace(_session.Token))
        {
            ErrorLabel.Text = "Sesión no disponible.";
            return;
        }

        try
        {
            IsLoading = true;
            ErrorLabel.Text = string.Empty;

            ApiResult<PagedData<ContentItem>> catalogResult = await _api.GetCatalogAsync(page: 1, pageSize: 12);
            ApiResult<ItemsResponse<SectionItem>> sectionResult = await _api.GetSectionsHomeAsync(_session.Token);

            if (!catalogResult.Success && !sectionResult.Success)
            {
                ErrorLabel.Text = $"{catalogResult.Message} / {sectionResult.Message}";
                return;
            }

            FeaturedItems.Clear();
            if (catalogResult.Success && catalogResult.Data is not null && catalogResult.Data.Items.Count > 0)
            {
                foreach (ContentItem item in catalogResult.Data.Items)
                {
                    FeaturedItems.Add(item);
                }
            }

            Sections.Clear();
            if (sectionResult.Success && sectionResult.Data is not null)
            {
                foreach (SectionItem section in sectionResult.Data.Items)
                {
                    Sections.Add(section);
                }

                if (FeaturedItems.Count == 0)
                {
                    foreach (ContentItem item in sectionResult.Data.Items.SelectMany(s => s.Items).Take(12))
                    {
                        FeaturedItems.Add(item);
                    }
                }
            }

            if (FeaturedItems.Count == 0)
            {
                ErrorLabel.Text = "No hay contenido disponible.";
            }
        }
        finally
        {
            IsLoading = false;
        }
    }

    private async void OnContentTapped(object? sender, TappedEventArgs e)
    {
        if (e.Parameter is not ContentItem item)
        {
            return;
        }

        await Navigation.PushAsync(new DetailPage(_api, _session, item.Slug, item.Title));
    }

}
