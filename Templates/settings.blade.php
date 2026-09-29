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
                    <input type="checkbox" name="hideOnboardingSteps" value="1" @if ($hideOnboardingSteps) checked @endif>
                    Use compact invited-user onboarding and skip theme, color, and schedule steps
                </label>
                <p>Compact onboarding keeps account setup, uses the current defaults for the skipped choices, and points new users to their profile settings for later customization.</p>
                <label class="lt-library-sortable__toggle">
                    <input type="checkbox" name="disableStarterProject" value="1" @if ($disableStarterProject) checked @endif>
                    Do not automatically create a starter “My Project” and its sample content
                </label>
                <p>Users without a project will be left on their dashboard or project hub without an auto-generated project.</p>

                <h2>To-do modal layout</h2>
                <p>Drag each tab or sidebar section between the visible and hidden areas, then arrange visible items in the order you want. Projects inherit this default until a project override is saved.</p>

                <section class="lt-library-layout-editor" data-library-layout-editor data-kind="tabs">
                    <h3>Modal tabs</h3>
                    <div class="lt-library-layout-editor__columns">
                        <div><h4>Shown in modal</h4><ol class="lt-library-layout-editor__lane" data-library-lane="visible">
                            @foreach ($tabs as $tab)
                                @if ($tab['enabled'])
                                    <li class="lt-library-layout-editor__item" draggable="true" data-widget-id="{{ $tab['id'] }}">
                                        <input type="hidden" name="tabOrder[]" value="{{ $tab['id'] }}">
                                        <input type="hidden" name="tabEnabled[]" value="{{ $tab['id'] }}" data-widget-enabled>
                                        <span class="lt-library-layout-editor__grip" aria-hidden="true">⠿</span>
                                        @if ($tab['icon'] !== '') <i class="{{ $tab['icon'] }}" aria-hidden="true"></i> @endif
                                        <span>{{ $tab['label'] }}</span><small>{{ $tab['builtin'] ? 'Leantime tab' : 'Plugin tab' }}</small>
                                        <button type="button" class="btn btn-default" data-widget-hide aria-label="Hide {{ $tab['label'] }}">Hide</button>
                                    </li>
                                @endif
                            @endforeach
                        </ol></div>
                        <div><h4>Hidden from modal</h4><ol class="lt-library-layout-editor__lane" data-library-lane="hidden">
                            @foreach ($tabs as $tab)
                                @if (!$tab['enabled'])
                                    <li class="lt-library-layout-editor__item" draggable="true" data-widget-id="{{ $tab['id'] }}">
                                        <input type="hidden" name="tabOrder[]" value="{{ $tab['id'] }}">
                                        <input type="hidden" name="tabEnabled[]" value="{{ $tab['id'] }}" data-widget-enabled disabled>
                                        <span class="lt-library-layout-editor__grip" aria-hidden="true">⠿</span>
                                        @if ($tab['icon'] !== '') <i class="{{ $tab['icon'] }}" aria-hidden="true"></i> @endif
                                        <span>{{ $tab['label'] }}</span><small>{{ $tab['builtin'] ? 'Leantime tab' : 'Plugin tab' }}</small>
                                        <button type="button" class="btn btn-default" data-widget-show aria-label="Show {{ $tab['label'] }}">Show</button>
                                    </li>
                                @endif
                            @endforeach
                        </ol></div>
                    </div>
                </section>

                <section class="lt-library-layout-editor" data-library-layout-editor data-kind="sections">
                    <h3>Details sidebar sections</h3>
                    <div class="lt-library-layout-editor__columns">
                        <div><h4>Shown in modal</h4><ol class="lt-library-layout-editor__lane" data-library-lane="visible">
                            @foreach ($sections as $section)
                                @if ($section['enabled'])
                                    <li class="lt-library-layout-editor__item" draggable="true" data-widget-id="{{ $section['id'] }}">
                                        <input type="hidden" name="sectionOrder[]" value="{{ $section['id'] }}">
                                        <input type="hidden" name="sectionEnabled[]" value="{{ $section['id'] }}" data-widget-enabled>
                                        <span class="lt-library-layout-editor__grip" aria-hidden="true">⠿</span>
                                        @if ($section['icon'] !== '') <i class="{{ $section['icon'] }}" aria-hidden="true"></i> @endif
                                        <span>{{ $section['label'] }}</span><small>{{ $section['builtin'] ? 'Leantime section' : 'Plugin section' }}</small>
                                        <button type="button" class="btn btn-default" data-widget-hide aria-label="Hide {{ $section['label'] }}">Hide</button>
                                    </li>
                                @endif
                            @endforeach
                        </ol></div>
                        <div><h4>Hidden from modal</h4><ol class="lt-library-layout-editor__lane" data-library-lane="hidden">
                            @foreach ($sections as $section)
                                @if (!$section['enabled'])
                                    <li class="lt-library-layout-editor__item" draggable="true" data-widget-id="{{ $section['id'] }}">
                                        <input type="hidden" name="sectionOrder[]" value="{{ $section['id'] }}">
                                        <input type="hidden" name="sectionEnabled[]" value="{{ $section['id'] }}" data-widget-enabled disabled>
                                        <span class="lt-library-layout-editor__grip" aria-hidden="true">⠿</span>
                                        @if ($section['icon'] !== '') <i class="{{ $section['icon'] }}" aria-hidden="true"></i> @endif
                                        <span>{{ $section['label'] }}</span><small>{{ $section['builtin'] ? 'Leantime section' : 'Plugin section' }}</small>
                                        <button type="button" class="btn btn-default" data-widget-show aria-label="Show {{ $section['label'] }}">Show</button>
                                    </li>
                                @endif
                            @endforeach
                        </ol></div>
                    </div>
                </section>

                <button class="btn btn-primary" type="submit">Save Library settings</button>
                <button class="btn btn-default" type="submit" name="resetLayout" value="1" formnovalidate>Reset To-do layout to defaults</button>
            </form>
        </div>
    </div>
@endsection
