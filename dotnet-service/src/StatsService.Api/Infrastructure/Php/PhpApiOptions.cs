using System.ComponentModel.DataAnnotations;

namespace StatsService.Api.Infrastructure.Php;

public sealed class PhpApiOptions
{
    public const string SectionName = "PhpApi";

    [Required, Url]
    public string BaseUrl { get; init; } = string.Empty;
}
