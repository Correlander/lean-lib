@extends($layout)

@section('content')
    <div class="maincontent">
        <div class="maincontentinner">
            @if (!empty($error))
                <div class="alert alert-danger" role="alert">{{ $error }}</div>
            @endif

            <form method="post" action="{{ BASE_URL }}/LeanLib/settings" data-library-settings-form>
                @csrf
                {!! $settingsContent !!}
            </form>
        </div>
    </div>
@endsection
