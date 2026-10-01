<section class="leantimelib-plugin-metadata" aria-label="Plugin metadata">
    <div class="leantimelib-plugin-metadata__body">
        <div class="leantimelib-plugin-metadata__facts">
            @if ($metadata['version'])
                <span class="leantimelib-plugin-metadata__pill leantimelib-plugin-metadata__version"><i class="fa-solid fa-tag" aria-hidden="true"></i><span>v{{ $metadata['version'] }}</span></span>
            @endif
            @if ($metadata['pluginFolder'])
                <span class="leantimelib-plugin-metadata__pill"><i class="fa-regular fa-folder" aria-hidden="true"></i><code>{{ $metadata['pluginFolder'] }}</code></span>
            @endif
            @if ($metadata['license'])
                <span class="leantimelib-plugin-metadata__pill"><i class="fa-regular fa-file-lines" aria-hidden="true"></i><span>{{ $metadata['license'] }}</span></span>
            @endif
            @if ($metadata['homepage'])
                @php
                    $websiteHost = parse_url($metadata['homepage'], PHP_URL_HOST) ?: 'Website';
                    if (str_starts_with(strtolower($websiteHost), 'www.')) $websiteHost = substr($websiteHost, 4);
                @endphp
                <a class="leantimelib-plugin-metadata__pill leantimelib-plugin-metadata__website" href="{{ $metadata['homepage'] }}" target="_blank" rel="noopener noreferrer" title="Open {{ $websiteHost }}">
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>Website</span><strong>{{ $websiteHost }}</strong>
                </a>
            @endif
        </div>

        @if ($metadata['authors'] !== [])
            <div class="leantimelib-plugin-metadata__authors" aria-label="Authors">
                @foreach ($metadata['authors'] as $author)
                    <span class="leantimelib-plugin-metadata__author">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        @if ($author['homepage'])<a class="leantimelib-plugin-metadata__profile" href="{{ $author['homepage'] }}" target="_blank" rel="noopener noreferrer">{{ $author['name'] }}</a>@else<span>{{ $author['name'] }}</span>@endif
                        @if ($author['email'])
                            <a href="mailto:{{ $author['email'] }}" aria-label="Email {{ $author['name'] }}" title="Email {{ $author['name'] }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i></a>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <nav class="leantimelib-plugin-metadata__links" aria-label="Plugin resources">
        @if ($metadata['sourceUrl'])
            <a class="leantimelib-plugin-metadata__link" href="{{ $metadata['sourceUrl'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-code-branch" aria-hidden="true"></i><span>Source</span></a>
        @endif
        @if ($metadata['supportUrl'])
            <a class="leantimelib-plugin-metadata__link" href="{{ $metadata['supportUrl'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-regular fa-circle-question" aria-hidden="true"></i><span>Support</span></a>
        @endif
        @if ($metadata['contributionsUrl'])
            <a class="leantimelib-plugin-metadata__link" href="{{ $metadata['contributionsUrl'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-heart" aria-hidden="true"></i><span>Contribute</span></a>
        @endif
    </nav>
</section>
