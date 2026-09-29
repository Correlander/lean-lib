@extends($layout)

@section('content')
    <div class="maincontent">
        <div class="maincontentinner">
            <header class="lt-library-page-heading">
                <h1>Correlander’s Leantime Library</h1>
                <p>One place to manage how enabled Leantime plugins and native interface components fit together.</p>
            </header>

            @if (!empty($error))
                <div class="alert alert-danger" role="alert">{{ $error }}</div>
            @endif

            @php
                $pluginTabs = array_filter($tabs, static fn ($tab) => !$tab['builtin']);
                $pluginSections = array_filter($sections, static fn ($section) => !$section['builtin']);
            @endphp
            @if (count($pluginTabs) === 0 && count($pluginSections) === 0)
                <div class="alert alert-info" role="status">
                    No enabled plugins have contributed To-do tabs or sidebar sections yet. Contributions will appear here when plugins register them with the Library.
                </div>
            @endif

            <form method="post" action="{{ BASE_URL }}/LeantimeLib/settings" data-library-settings-form>
                @csrf
                <section class="lt-library-preferences">
                    <header>
                        <h2>General improvements</h2>
                        <p>Optional changes to Leantime’s navigation and onboarding.</p>
                    </header>
                    <label class="lt-library-preference">
                        <input type="checkbox" name="hideExploreApps" value="1" @if ($hideExploreApps) checked @endif>
                        <span><strong>Make My Apps the only Apps page</strong><small>Hide Explore Apps and send the Apps menu directly to My Apps.</small></span>
                    </label>
                    <label class="lt-library-preference">
                        <input type="checkbox" name="fastOnboarding" value="1" @if ($fastOnboarding) checked @endif>
                        <span><strong>Fast Onboarding</strong><small>Skip appearance and schedule steps, avoid creating a starter “My Project,” and let users adjust their schedule later in Profile settings.</small></span>
                    </label>
                </section>

                <section class="lt-library-gui-customization">
                    <header>
                        <h2>GUI customization</h2>
                        <p>Arrange native interface parts and plugin contributions through one shared layout. Plugins provide their widgets; the Library controls where they appear and which ones are visible.</p>
                    </header>
                    <label class="lt-library-editor-selector">Editing
                        <select aria-label="Choose interface area to customize">
                            <option>To-do modal</option>
                        </select>
                    </label>
                    {!! $todoLayoutEditor !!}
                </section>
                <p class="lt-library-autosave-status" data-autosave-status role="status" aria-live="polite">Changes save automatically.</p>
            </form>
        </div>
    </div>
@endsection
