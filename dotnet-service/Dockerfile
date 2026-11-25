FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
WORKDIR /src

COPY StatsService.sln ./
COPY src/StatsService.Api/StatsService.Api.csproj src/StatsService.Api/
COPY tests/StatsService.Tests/StatsService.Tests.csproj tests/StatsService.Tests/
RUN dotnet restore

COPY . .
RUN dotnet publish src/StatsService.Api -c Release -o /app --no-restore

FROM mcr.microsoft.com/dotnet/aspnet:10.0
WORKDIR /app
COPY --from=build /app .

# Npgsql intenta cargar GSSAPI al conectar; sin la librería ensucia los logs con un error en texto plano
RUN apt-get update \
    && apt-get install -y --no-install-recommends libgssapi-krb5-2 \
    && rm -rf /var/lib/apt/lists/*

ENV ASPNETCORE_URLS=http://+:5000
EXPOSE 5000

USER app
ENTRYPOINT ["dotnet", "StatsService.Api.dll"]
