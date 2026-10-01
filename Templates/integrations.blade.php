<h1>Integrations</h1>
<p>Installed apps and connected services for this Leantime instance.</p>
<nav class="lt-library-integrations-tabs" aria-label="Integrations">
    <a href="{{ BASE_URL }}/plugins/myapps">My Apps</a>
    <a href="{{ BASE_URL }}/connector/show">Connected services</a>
</nav>
<section class="lt-library-integrations-content" data-library-integrations-page>
            <h2>Installed apps</h2>
            <div class="row sortableTicketList lt-library-app-grid">
                @foreach (($installedPlugins ?: []) as $plugin)
                    <div class="col-md-4">
                        <article class="ticketBox fixed" style="padding-top:0; overflow:hidden; margin-bottom:25px">
                            <div class="row"><div class="col-md-12 tw-overflow-hidden tw-mb-m">
                                <img src="{{ $plugin->getPluginImageData() }}" width="75" height="75" class="tw-rounded tw-mt-base" alt="">
                                <div class="clearall"></div>
                                <div style="margin-top:10px">
                                    @if (!empty($plugin->name))
                                        <strong style="font-size:var(--font-size-l)">{!! $plugin->name !!}</strong> @if (!empty($plugin->version)) <small>(v{{ $plugin->version }})</small> @endif<br>
                                        <x-global::inlineLinks :links="$plugin->getMetadataLinks()" />
                                    @endif
                                </div>
                            </div></div>
                            <div class="row tw-mb-base"><div class="col tw-flex tw-flex-col tw-gap-base">
                                @if (!empty($description = $plugin->getCardDesc())) <p>{!! $description !!}</p> @endif
                                <div class="tw-flex tw-flex-row tw-gap-base">
                                    <div class="plugin-price tw-flex-1 tw-content-center"><strong>{!! $plugin->getPrice() !!}</strong></div>
                                    <div class="tw-border-t tw-border-[var(--main-border-color)] tw-px-base tw-text-right tw-flex-1 tw-justify-items-end">
                                        @include($plugin->getControlsView(), ['plugin' => $plugin])
                                    </div>
                                </div>
                            </div></div>
                        </article>
                    </div>
                @endforeach
                @if (empty($installedPlugins)) <p>No plugins are installed.</p> @endif
            </div>
            <h2>Apps ready to install</h2>
            <div class="row lt-library-app-grid">
                @foreach (($newPlugins ?: []) as $plugin)
                    <div class="col-md-4">
                        <article class="ticketBox">
                            <strong>{{ $plugin->name }}</strong>
                            <p>{{ $plugin->description }}</p>
                            <small>Version {{ $plugin->version }}</small>
                            <form method="post" action="{{ BASE_URL }}/LeanLib/integrations/activate">
                                @csrf
                                <input type="hidden" name="plugin" value="{{ $plugin->foldername }}">
                                <button class="btn btn-default" type="submit">Activate</button>
                            </form>
                        </article>
                    </div>
                @endforeach
                @if (empty($newPlugins)) <p>No new plugins are available in the plugins folder.</p> @endif
            </div>
</section>
