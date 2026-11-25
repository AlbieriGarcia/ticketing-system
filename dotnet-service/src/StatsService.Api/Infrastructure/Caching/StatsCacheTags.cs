namespace StatsService.Api.Infrastructure.Caching;

// Los endpoints etiquetan lo que cachean y los mensajes invalidan por etiqueta
public static class StatsCacheTags
{
    public const string Summary = "summary";

    public static string Event(long eventId) => $"event:{eventId}";
}
