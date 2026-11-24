namespace StatsService.Api.Infrastructure.Errors;

public sealed class EventNotFoundException(long eventId)
    : Exception($"Event {eventId} was not found.");

public sealed class UpstreamUnavailableException(string message, Exception? inner = null)
    : Exception(message, inner);
