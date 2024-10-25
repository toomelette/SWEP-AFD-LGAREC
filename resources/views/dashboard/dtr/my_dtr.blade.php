@extends('layouts.admin-master')

@section('content')

    @php
        $bm_uid = 0;

        if(!empty($employee->biometric_user_id)){
            $bm_uid = $employee->biometric_user_id;
        }
    @endphp
    <section class="content-header">
        <h1>Daily Time Record</h1>
    </section>
@endsection
@section('content2')

    <section class="content">
        <div class="box box-success">
            <div class="box-header with-border" >
                <h3 class="box-title">Daily Time Record</h3>
                @php
                    $cl = \App\Models\CronLogs::query()->where('log','like','%Reconstructed%')->orderBy('created_at','desc')->first();
                @endphp
                <h4 class="box-title pull-right text-muted" style="font-size: 1.5rem">
                    <i class="fa fa-clock-o"></i> Last updated :
                    @if(!empty($cl))
                        {{\Carbon\Carbon::parse($cl->created_at)->format('M. d, Y | h:i A')}}
                    @endif
                </h4>
            </div>
            <div class="box-body">
                <div class="box-group" id="accordion">

                    @foreach($dtr_by_year as $key => $months)
                        @php(arsort($months))

                        @php($must_be_last = \Illuminate\Support\Str::after(array_key_first($months),'-')*1)
                        @for($x = 1 ; $x <= $must_be_last; $x++)
                            @if(!isset($dtr_by_year[$key][$key.'-'.str_pad($x,2,'0',STR_PAD_LEFT)]))
                                @php($dtr_by_year[$key][$key.'-'.str_pad($x,2,'0',STR_PAD_LEFT)] = '')
                            @endif
                        @endfor

                    @endforeach


                    @if(count($dtr_by_year) > 0)
                        @php($num=0)
                        @foreach($dtr_by_year as $key => $months)
                            @php(ksort($months))
                            @php($num++)
                            @if($num == 1)
                                <div class="panel box box-primary">
                                    <div class="box-header with-border">
                                        <h4 class="box-title">
                                            <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="true" class="">
                                                {{$key}}
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapseOne" class="panel-collapse collapse in" aria-expanded="true" style="" >
                                        <div class="box-body">
                                            @if(count($months) > 0)
                                                @php(ksort($months))
                                                <div class="row">
                                                @foreach($months as $month => $null)
                                                    <div class="{{$col}}">
                                                        @if(\Carbon\Carbon::parse($month)->format('Y-m') == \Carbon\Carbon::now()->format('Y-m'))
                                                            @php($class = 'btn-success')
                                                        @else
                                                            @php($class = 'btn-default')
                                                        @endif
                                                        <button style="width: 100%; margin-bottom: 10px" type="button" class="btn {{$class}}  month_btn" data-toggle="modal" data-target="#dtr_modal" month="{{$month}}">
                                                            {{strtoupper(\Carbon\Carbon::parse($month)->format('M'))}}
                                                        </button>
                                                    </div>
                                                @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="panel box box-primary">
                                    <div class="box-header with-border">
                                        <h4 class="box-title">
                                            <a data-toggle="collapse" data-parent="#accordion" href="#collapse{{$key}}" class="collapsed" aria-expanded="false">
                                                {{$key}}
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapse{{$key}}" class="panel-collapse collapse" aria-expanded="false" style="height: 0px;">
                                        <div class="box-body">
                                            @if(count($months) > 0)
                                                @php(ksort($months))
                                                <div class="row">
                                                    @foreach($months as $month => $null)
                                                        <div class="{{$col}}">
                                                            <button type="button" style="width: 100%; margin-bottom: 10px" class="btn btn-default month_btn" data-toggle="modal" data-target="#dtr_modal" month="{{$month}}">
                                                                {{strtoupper(\Carbon\Carbon::parse($month)->format('M'))}}
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach


                    @else
                        <div class="callout callout-success">
                            <h4><i class="fa fa-info-circle"></i> No attendance record found.</h4>
                        </div>
                    @endif
                </div>
            </div>
            <!-- /.box-body -->
        </div>
        <div id="frameee">

        </div>
    </section>


@endsection


@section('modals')
    {!! __html::blank_modal('dtr_modal','lg') !!}
    <div class="modal fade" id="print_dtr_modal" role="dialog" aria-labelledby="print_dtr_modal_label">
      <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <form id="print_dtr_form">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Print DTR</h4>
              </div>
              <div class="modal-body">

                    <div class="row">
                        <input name="month" hidden>
                        <input name="bm_u_id" hidden>
                        {!! \App\Swep\ViewHelpers\__form2::textbox('official_name',[
                            'label' => 'Authorized Official:',
                            'cols' => 12,
                            'required' => 'required',
                        ]) !!}
                        {!! \App\Swep\ViewHelpers\__form2::textbox('official_position',[
                            'label' => 'Position:',
                            'cols' => 12,
                            'required' => 'required',
                        ]) !!}
                    </div>

              </div>
              <div class="modal-footer">
                  <button type="submit" class="btn btn-primary"><i class="fa fa-print"></i> Print</button>
              </div>
            </form>
        </div>
      </div>
    </div>
@endsection

@section('scripts')
    <script type="text/javascript">

        $('#dtr_modal').on('shown.bs.modal', function() {
            $(document).off('focusin.modal');
        });


        function dt_draw() {
            users_table.draw(false);
        }

        function filter_dt() {
            is_online = $(".filter_status").val();
            is_active = $(".filter_account").val();
            users_table.ajax.url("{{ route('dashboard.user.index') }}" + "?is_online=" + is_online + "&is_active=" + is_active).load();

            $(".filters").each(function (index, el) {
                if ($(this).val() != '') {
                    $(this).parent("div").addClass('has-success');
                    $(this).siblings('label').addClass('text-green');
                } else {
                    $(this).parent("div").removeClass('has-success');
                    $(this).siblings('label').removeClass('text-green');
                }
            });
        }
    </script>
    <script type="text/javascript">
        modal_loader = $("#modal_loader").parent('div').html();
        $(document).ready(function () {



        })
        $('body').on('click','.month_btn',function () {
            btn = $(this);
            var month = $(this).attr('month');
            var bm_u_id = "{{$bm_uid}}";
            load_modal2(btn);
            $.ajax({
                url : '{{route("dashboard.dtr.fetch_by_user_and_month")}}',
                data : {bm_u_id : bm_u_id, month: month},
                type: 'GET',
                success: function (res) {
                   populate_modal2(btn,res);
                },
                error: function (res) {
                    populate_modal2_error(res);
                }
            })
        })

        $("body").on("click",".fc-day-grid-event",function (e) {
            e.preventDefault();
            if($(this).attr('href') != 'undefined' && $(this).attr('href') !== false){
                Swal.fire(
                    'Details:',
                    $(this).attr('href'),
                    'info',
                )
            }
        })

        $("#capture_btn").click(function () {
            html2canvas(document.querySelector(".box-success")).then(canvas => {
                $('#frameee').append(canvas);
            });
        });
        $("#print_dtr_form").submit(function (e) {
            e.preventDefault();
            let form = $(this);
            let month = form.find('input[name=month]').val();
            let bm_u_id = form.find('input[name=bm_u_id]').val();
            $("#print_frame").attr('src','{{route("dashboard.dtr.download")}}?'+form.serialize());
            Swal.fire({
                icon: 'info',
                title: 'Please wait...',
                html: '<div style="padding: 15px; font-size: larger"><i class="fa fa-spin fa-spinner"></i> Preparing your DTR. . .</div>',
                showConfirmButton : false,
            })
        })
    </script>
@endsection