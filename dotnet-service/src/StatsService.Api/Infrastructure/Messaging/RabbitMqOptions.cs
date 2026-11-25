using System.ComponentModel.DataAnnotations;

namespace StatsService.Api.Infrastructure.Messaging;

public sealed class RabbitMqOptions
{
    public const string SectionName = "RabbitMq";

    // Permite apagar el consumidor en tests o en entornos sin broker
    public bool Enabled { get; init; } = true;

    [Required]
    public string Host { get; init; } = string.Empty;

    [Range(1, 65535)]
    public int Port { get; init; } = 5672;

    [Required]
    public string UserName { get; init; } = string.Empty;

    [Required]
    public string Password { get; init; } = string.Empty;

    public string VirtualHost { get; init; } = "/";

    [Required]
    public string Exchange { get; init; } = "ticketing";

    [Required]
    public string Queue { get; init; } = "stats-service.ticketing";
}
