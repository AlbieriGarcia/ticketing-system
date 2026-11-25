using System.Text.Json;
using System.Text.Json.Serialization;

namespace StatsService.Api.Infrastructure.Php;

public static class PhpJson
{
    // PHP serializa los decimales como string ("30000.00") y usa snake_case, tanto en la API como en los mensajes
    public static readonly JsonSerializerOptions Options = new(JsonSerializerDefaults.Web)
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        NumberHandling = JsonNumberHandling.AllowReadingFromString,
    };
}
