@extends($layout)

@section('content')
    <div class="maincontent">
        <div class="maincontentinner">
            <h1>Leantime Library</h1>
            <p>Arrange the native To-do tabs and sidebar sections together with contributions from enabled plugins. Enable or hide plugin contributions here; provider plugins supply their content while the Library owns placement and ordering.</p>

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

            <form method="post" action="{{ BASE_URL }}/LeantimeLib/settings">
                @csrf
                <h2>Leantime interface</h2>
                <p>Optional changes to Leantime’s Apps navigation.</p>
                <label class="lt-library-sortable__toggle">
                    <input type="checkbox" name="hideExploreApps" value="1" @if ($hideExploreApps) checked @endif>
                    Hide Explore Apps and make My Apps the only Apps tab and destination
                </label>
                <label class="lt-library-sortable__toggle">
                    <input type="checkbox" name="fastOnboarding" value="1" @if ($fastOnboarding) checked @endif>
                    Fast Onboarding: skip appearance and schedule steps, and do not create a starter “My Project”
                </label>
                <p>Fast Onboarding keeps account setup, uses appearance and schedule defaults, and sends users without a project to their dashboard. Users can change their work hours later under Profile settings.</p>

                {!! $todoLayoutEditor !!}

                <button class="btn btn-primary" type="submit">Save Library settings</button>
                <button class="btn btn-default" type="submit" name="resetLayout" value="1" formnovalidate>Reset To-do layout to defaults</button>
            </form>
        </div>
    </div>
@endsection
