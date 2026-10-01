@extends($layout)

@section('content')
    <div class="maincontent">
        {!! $tpl->displayNotification() !!}
        <div class="maincontentinner">
            {!! $settingsContent !!}
        </div>
    </div>
@endsection
