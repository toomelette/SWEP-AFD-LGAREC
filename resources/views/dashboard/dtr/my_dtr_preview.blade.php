@php
    $ud = \App\Models\UserData::query()->where('user_id','=',\Illuminate\Support\Facades\Auth::user()->user_id)
            ->where('data','=','dtr_edit_intro')
            ->first();

    if(empty(\App\Swep\Helpers\Helper::checkRouteAccess('dashboard.dtr.store'))){
        if($employee->biometric_user_id !== \Illuminate\Support\Facades\Auth::user()->employee->biometric_user_id){
            $editable_class = '';
            $editable_remarks_class = '';
            $editable_lt_ut = '';
        }else{
            $editable_class = 'non-editable-dtr';
            $editable_remarks_class = 'editable-remarks';
            $editable_lt_ut = 'non-editable-lt-uts';
        }
    }else{
        $editable_class = 'non-editable-dtr';
        $editable_remarks_class = 'editable-remarks';
        $editable_lt_ut = 'non-editable-lt-ut';
    }

@endphp
@extends('layouts.modal-content')

@section('modal-header')
{{\Carbon\Carbon::parse($month)->format('F, Y')}} - {{strtoupper($employee->lastname)}}, {{strtoupper($employee->firstname)}} {{strtoupper($employee->name_ext)}}
@endsection

@section('modal-body')
  <style>
      .fc-basic-view .fc-body .fc-row{min-height:8em !important;}
  </style>
    @php
        $days_in_this_month = \Carbon\Carbon::parse($month)->daysInMonth
    @endphp
{{--    {{print_r($dtr_array)}}--}}
    <form method="POST" id="download_form" action="{{route('dashboard.dtr.download')}}">
        @csrf
        <input value="" id="sup_name" name="sup_name" hidden>
        <input value="{{$bm_u_id}}" id="" name="bm_u_id" hidden>
        <input value="{{$month}}" name="month" hidden>
    </form>
    <button class="btn btn-primary pull-right" data-toggle="modal" data-target="#print_dtr_modal" id="print_dtr_btn"><i class="fa fa-print"></i> Print</button>
{{--    <button type="submit" class="btn btn-primary pull-right download_btn" style="margin-bottom: 1rem"><i class="fa fa-download"></i> Download PDF </button>--}}
    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
            <li class="active"><a href="#tab_1" data-toggle="tab"><i class="fa fa-clock-o"></i> DAILY TIME RECORD</a></li>
            <li><a href="#tab_2" data-toggle="tab"><i class="fa fa-calendar-check-o"></i> ATTENDANCE LOGS</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane active" id="tab_1">
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed">
                        <thead>
                        <tr>
                            <th rowspan="2" style="vertical-align : middle;text-align:center;">Date</th>
                            <th colspan="2" class="text-center">Morning</th>
                            <th colspan="2" class="text-center">Afternoon</th>
                            <th colspan="2" class="text-center">Overtime</th>
                            <th rowspan="2" style="vertical-align : middle;text-align:center;">Late</th>
                            <th rowspan="2" style="vertical-align : middle;text-align:center;">Undertime</th>
                            <th rowspan="2" style="vertical-align : middle;text-align:center;">Remarks</th>
                        </tr>
                        <tr>
                            <th class="text-center" style="min-width: 40px">IN</th>
                            <th class="text-center" style="min-width: 40px">OUT</th>
                            <th class="text-center" style="min-width: 40px">IN</th>
                            <th class="text-center" style="min-width: 40px">OUT</th>
                            <th class="text-center" style="min-width: 40px">IN</th>
                            <th class="text-center" style="min-width: 40px">OUT</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $late = 0;
                            $undertime = 0;
                            $saturdays= 0;
                            $sundays = 0;
                            $intro_num = 0;
                        @endphp


                        @for($a = 1 ; $a <= $days_in_this_month; $a++)
                            @php
                                $date = sprintf('%02d', $a);
                                $fullDate = $month.'-'.$date;

                                if($month.'-'.$date == \Carbon\Carbon::now()->format('Y-m-d')){
                                    $mark = 'info';
                                }else{
                                    $mark ='';
                                }
                            @endphp


{{--                            IF THIS DATE HAS A RECORD--}}
                            @if(isset($dtr_array[$month.'-'.$date]))

                                @php
                                    $late =  $late + $dtr_array[$month.'-'.$date]->late;
                                    $undertime = $undertime + $dtr_array[$month.'-'.$date]->undertime;
                                    if($dtr_array[$month.'-'.$date]->calculated == -1){
                                        $italic_op = '<i style="color:#e6a800">';
                                        $italic_cl = '</i>';
                                    }else{
                                        $italic_op = '';
                                        $italic_cl = '';
                                    }
                                @endphp

                                <tr class="text-center {{$mark}}">
                                    <td>
                                        {{$date}}
                                    </td>
                                    <td  id="{{Str::random(8)}}" class="{{$editable_class}}" val="{{$dtr_array[$month.'-'.$date]->am_in}}" data="{{$month}}-{{$date}}" data-type="am_in" title="Click to edit Time">
                                        @if(isset($dtr_edits_array[$fullDate]['am_in']))
                                            <span class="text-red">{{$dtr_edits_array[$fullDate]['am_in']}}</span>
                                        @else
                                            {!! __html::dtrTime($dtr_array[$month.'-'.$date]->am_in) !!}
                                        @endif
                                    </td>
                                    <td  id="{{Str::random(8)}}" class="{{$editable_class}}" val="{{$dtr_array[$month.'-'.$date]->am_out}}" data="{{$month}}-{{$date}}" data-type="am_out" title="Click to edit Time">
                                        @if(isset($dtr_edits_array[$fullDate]['am_out']))
                                            <span class="text-red">{{$dtr_edits_array[$fullDate]['am_out']}}</span>
                                        @else
                                            {!! __html::dtrTime($dtr_array[$month.'-'.$date]->am_out) !!}
                                        @endif

                                    </td>
                                    <td  id="{{Str::random(8)}}" class="{{$editable_class}}" val="{{$dtr_array[$month.'-'.$date]->pm_in}}" data="{{$month}}-{{$date}}" data-type="pm_in" title="Click to edit Time">
                                        @if(isset($dtr_edits_array[$fullDate]['pm_in']))
                                            <span class="text-red">{{$dtr_edits_array[$fullDate]['pm_in']}}</span>
                                        @else
                                            {!! __html::dtrTime($dtr_array[$month.'-'.$date]->pm_in) !!}
                                        @endif
                                    </td>
                                    <td  id="{{Str::random(8)}}" class="{{$editable_class}}" val="{{$dtr_array[$month.'-'.$date]->pm_out}}" data="{{$month}}-{{$date}}" data-type="pm_out" title="Click to edit Time">
                                        @if(isset($dtr_edits_array[$fullDate]['pm_out']))
                                            <span class="text-red">{{$dtr_edits_array[$fullDate]['pm_out']}}</span>
                                        @else
                                            {!! __html::dtrTime($dtr_array[$month.'-'.$date]->pm_out) !!}
                                        @endif
                                    </td>
                                    <td>
                                        {!! __html::dtrTime($dtr_array[$month.'-'.$date]->ot_in) !!}
                                    </td>
                                    <td>
                                        {!! __html::dtrTime($dtr_array[$month.'-'.$date]->ot_out) !!}
                                    </td>

                                    <td id="{{\Illuminate\Support\Str::random(9)}}" class="{{$editable_lt_ut}}" data-type="late" data="{{$dtr_array[$month.'-'.$date]->id}}">
                                        @if($dtr_array[$month.'-'.$date]->date != \Carbon\Carbon::now()->format('Y-m-d'))
                                            {!! $italic_op !!}
                                            {{$dtr_array[$month.'-'.$date]->late == 0 ? '' : \App\Swep\Helpers\Helper::convertToHoursMins($dtr_array[$month.'-'.$date]->late)}}
                                            {!! $italic_cl !!}
                                        @endif

                                    </td>
                                    <td id="{{\Illuminate\Support\Str::random(9)}}" class="{{$editable_lt_ut}}" data-type="undertime" data="{{$dtr_array[$month.'-'.$date]->id}}">
                                        @if($dtr_array[$month.'-'.$date]->date != \Carbon\Carbon::now()->format('Y-m-d'))
                                            {!! $italic_op !!}
                                            {{$dtr_array[$month.'-'.$date]->undertime == 0 ? '' : \App\Swep\Helpers\Helper::convertToHoursMins($dtr_array[$month.'-'.$date]->undertime)}}
                                            {!! $italic_cl !!}
                                        @endif
                                    </td>
                                    <td class="text-left {{$editable_remarks_class}}" id="a{{\Illuminate\Support\Str::random(8)}}" data="{{$month}}-{{$date}}" title="Click to edit remark">
                                        @if(\Carbon\Carbon::parse($month.'-'.$date)->format('w') == 6)
                                            @php
                                                $saturdays++;
                                            @endphp
                                            SATURDAY
                                        @endif
                                        @if(\Carbon\Carbon::parse($month.'-'.$date)->format('w') == 0)
                                            @php
                                                $sundays++;
                                            @endphp
                                            SUNDAY
                                        @endif
                                        @if(isset($holidays[$month.'-'.$date]))
                                            <b>{{$holidays[$month.'-'.$date]['type']}}</b>
                                        @endif
{{--                                        @if($dtr_array[$month.'-'.$date]->calculated == -1)--}}
{{--                                            <span class="text-danger">INC</span>--}}
{{--                                        @endif--}}
                                            <span class="text-red">{{$dtr_array[$month.'-'.$date]->remarks}}</span>
                                    </td>
                                </tr>
                            @else
                                @php
                                    $intro_num++;
                                @endphp
                                    <tr class="text-center {{$mark}} {{($intro_num == 1) ? 'first-empty-cell':''}}">
                                        <td>
                                            {{$date}}
                                        </td>
                                        <td id="{{Str::random(8)}}" class="{{$editable_class}}" val="{{\Carbon::now()->format('H:i')}}" data="{{$month}}-{{$date}}" data-type="am_in" title="Click to edit Time"></td>
                                        <td id="{{Str::random(8)}}" class="{{$editable_class}}" val="{{\Carbon::now()->format('H:i')}}" data="{{$month}}-{{$date}}" data-type="am_out" title="Click to edit Time"></td>
                                        <td id="{{Str::random(8)}}" class="{{$editable_class}}" val="{{\Carbon::now()->format('H:i')}}" data="{{$month}}-{{$date}}" data-type="pm_in" title="Click to edit Time"></td>
                                        <td id="{{Str::random(8)}}" class="{{$editable_class}}" val="{{\Carbon::now()->format('H:i')}}" data="{{$month}}-{{$date}}" data-type="pm_out" title="Click to edit Time"></td>
                                        <td></td>
                                        <td></td>
                                        <td id="{{\Illuminate\Support\Str::random(9)}}" class="{{$editable_lt_ut}}"></td>
                                        <td id="{{\Illuminate\Support\Str::random(9)}}" class="{{$editable_lt_ut}}"></td>
                                        <td class="text-left {{$editable_remarks_class}}" id="a{{\Illuminate\Support\Str::random(8)}}" data="{{$month}}-{{$date}}" title="Click to edit remark">
                                            @if(\Carbon\Carbon::parse($month.'-'.$date)->format('w') == 6)
                                                @php
                                                    $saturdays++;
                                                @endphp
                                                SAT
                                            @endif
                                            @if(\Carbon\Carbon::parse($month.'-'.$date)->format('w') == 0)
                                                @php
                                                    $sundays++;
                                                @endphp
                                                SUN
                                            @endif
                                            @if(isset($holidays[$month.'-'.$date]))
                                                <b>HOL</b>
                                            @endif

                                        </td>
                                    </tr>
                            @endif
                        @endfor
                        </tbody>
                    </table>
                </div>


                <div class="row">
                    <div class="col-md-6">
                        <dl class="dl-horizontal">
                            <dt>Total Late:</dt>
                            <dd>{{\App\Swep\Helpers\Helper::convertToHoursMins($late)}}</dd>
                            <dt>Total Undertime:</dt>
                            <dd>{{\App\Swep\Helpers\Helper::convertToHoursMins($undertime)}}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="dl-horizontal">
                            <dt>Total Saturdays:</dt>
                            <dd>{{number_format($saturdays)}}</dd>
                            <dt>Total Sundays:</dt>
                            <dd>{{number_format($sundays)}}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <!-- /.tab-pane -->
            <div class="tab-pane" id="tab_2">
                <div class="box-body no-padding">
                    <!-- THE CALENDAR -->
                    <div id="calendar">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div style="">

    </div>

@endsection

@section('modal-footer')
    <iframe id="print_frame" src="" class="pull-left"  width="1" height="1"></iframe>
    <button type="button" class="btn btn-default pull-right" data-dismiss="modal">Close</button>
@endsection

@section('scripts')
<script>
    $(function () {

        /* initialize the external events
         -----------------------------------------------------------------*/
        function init_events(ele) {
            ele.each(function () {
                // create an Event Object (http://arshaw.com/fullcalendar/docs/event_data/Event_Object/)
                // it doesn't need to have a start or end
                var eventObject = {
                    title: $.trim($(this).text()) // use the element's text as the event title
                }

                // store the Event Object in the DOM element so we can get to it later
                $(this).data('eventObject', eventObject)

                // make the event draggable using jQuery UI
                $(this).draggable({
                    zIndex        : 1070,
                    revert        : true, // will cause the event to go back to its
                    revertDuration: 0  //  original position after the drag
                })

            })
        }

        init_events($('#external-events div.external-event'))

        /* initialize the calendar
         -----------------------------------------------------------------*/
        //Date for the calendar events (dummy data)
        var date = new Date()
        var d    = date.getDate(),
            m    = date.getMonth(),
            y    = date.getFullYear()
        $('#calendar').fullCalendar({
            // header    : {
            //     left  : 'prev,next today',
            //     center: 'title',
            //     right : 'month,agendaWeek,agendaDay'
            // },
            // buttonText: {
            //     today: 'today',
            //     month: 'month',
            //     week : 'week',
            //     day  : 'day'
            // },
            //Random default events
            events    : [
                    @if(!empty($attendance_logs))
                        @foreach($attendance_logs as $attendance_log)
                            {
                                title          : "{{$biometric_values[$attendance_log->type]}}",
                                start          : new Date("{{Carbon::parse($attendance_log->timestamp)->format('Y-m-d').'T'.Carbon::parse($attendance_log->timestamp)->format('H:i:s')}}"),
                                backgroundColor: "{{\App\Swep\Helpers\Helper::biometricValuesColor($attendance_log->type)}}", //red
                                borderColor    : "{{\App\Swep\Helpers\Helper::biometricValuesColor($attendance_log->type)}}",
                                    url: "Biometric device: {{(!empty($attendance_log->deviceDetails->name) ? $attendance_log->deviceDetails->name : 'UNKNOWN')}}",
                            },
                        @endforeach
                    @endif
                    @if(count($holidays)>0)
                        @foreach($holidays as $key => $holiday)
                        {
                            title          : "{{$holiday['name']}}",
                            start          : new Date("{{Carbon::parse($holiday['date'])->format('Y-m-d')}}T00:00:00"),
                            backgroundColor: "#dd4b39", //red
                            borderColor    : "#dd4b39",
                            url            : "{{$holiday['type']}}",
                        },
                        @endforeach
                    @endif
            ],

            editable  : false,
            droppable : false, // this allows things to be dropped onto the calendar !!!
            drop      : function (date, allDay) { // this function is called when something is dropped

                // retrieve the dropped element's stored Event Object
                var originalEventObject = $(this).data('eventObject')

                // we need to copy it, so that multiple events don't have a reference to the same object
                var copiedEventObject = $.extend({}, originalEventObject)

                // assign it the date that was reported
                copiedEventObject.start           = date
                copiedEventObject.allDay          = allDay
                copiedEventObject.backgroundColor = $(this).css('background-color')
                copiedEventObject.borderColor     = $(this).css('border-color')

                // render the event on the calendar
                // the last `true` argument determines if the event "sticks" (http://arshaw.com/fullcalendar/docs/event_rendering/renderEvent/)
                $('#calendar').fullCalendar('renderEvent', copiedEventObject, true)

                // is the "remove after drop" checkbox checked?
                if ($('#drop-remove').is(':checked')) {
                    // if so, remove the element from the "Draggable Events" list
                    $(this).remove()
                }

            }
        })

        $('#calendar').fullCalendar('gotoDate', '{{$fullDate}}');

        /* ADDING EVENTS */
        var currColor = '#3c8dbc' //Red by default
        //Color chooser button
        var colorChooser = $('#color-chooser-btn')
        $('#color-chooser > li > a').click(function (e) {
            e.preventDefault()
            //Save color
            currColor = $(this).css('color')
            //Add color effect to button
            $('#add-new-event').css({ 'background-color': currColor, 'border-color': currColor })
        })


        $('#add-new-event').click(function (e) {
            e.preventDefault()
            //Get value and make sure it is not null
            var val = $('#new-event').val()
            if (val.length == 0) {
                return
            }

            //Create events
            var event = $('<div />')
            event.css({
                'background-color': currColor,
                'border-color'    : currColor,
                'color'           : '#fff'
            }).addClass('external-event')
            event.html(val)
            $('#external-events').prepend(event)

            //Add draggable funtionality
            init_events(event)

            //Remove event from text input
            $('#new-event').val('')
        })
    })

    setTimeout(function () {
        $("#calendar .fc-header-toolbar").remove();
    },100);

    $(".download_btn").click(function (e) {
        let timerInterval
        Swal.fire({
            title: 'Downloading, please wait . . .',
            html: 'This dialog will close in <b>3</b> seconds.',
            timer: 3000,
            timerProgressBar: true,
            didOpen: () => {
                Swal.showLoading()
                const b = Swal.getHtmlContainer().querySelector('b')
                timerInterval = setInterval(() => {
                    b.textContent = Math.round(Swal.getTimerLeft()/1000);
                }, 1000)
                $("#download_form").submit();
            },
            willClose: () => {
                clearInterval(timerInterval)
            }
        }).then((result) => {
            /* Read more about handling dismissals below */
            if (result.dismiss === Swal.DismissReason.timer) {
               //console.log('I was closed by the timer')
            }
        })


    })
    $('#print_dtr_btn').click(function () {

        {{--$("#print_frame").attr('src','{{route("dashboard.dtr.download")}}?month={{$month}}&bm_u_id={{$bm_u_id}}');--}}

        $("#print_dtr_form input[name=month]").val('{{$month}}');
        $("#print_dtr_form input[name=bm_u_id]").val('{{$bm_u_id}}');
    })

</script>

    <script type="text/javascript">
        function loaded(){
            Swal.close();
            $('#print_dtr_modal').modal('hide');
            $('#print_frame').get(0).contentWindow.print();
        }

    @if(!empty(\App\Swep\Helpers\Helper::checkRouteAccess('dashboard.dtr.store')) || $employee->biometric_user_id == \Illuminate\Support\Facades\Auth::user()->employee->biometric_user_id)
        $(".editable-dtr").click(function () {
            let btn = $(this);
            let date = btn.attr('data');
            btn.addClass('active');
            Swal.fire({
                title: 'Enter Time:',
                html: '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Note:<br></span>' +
                    'An edited time record must be supported with a Permission Slip or Timekeeping Slip upon submission to the HR. ' +
                    '<br><br><b class="text-danger">THIS ACTION CANNOT BE UNDONE</b><br><input id="time_record_input" value="'+btn.attr('val')+'" type="time" class="form-control time_input_dtr" autofocus>',
                inputAttributes: {
                    autocapitalize: 'off'
                },
                backdrop : true,
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-check"></i> Save Changes',
                showLoaderOnConfirm: true,
                preConfirm: (login) => {
                    return $.ajax({
                        url : '{{route('dashboard.dtr.update_time_record')}}',
                        type: 'POST',
                        data: {
                            'date':date,
                            'biometric_user_id':'{{$bm_u_id}}',
                            'employee_no' : '{{$employee->employee_no}}',
                            'time' : $("#time_record_input").val(),
                            'type' : btn.attr('data-type'),
                            'element_id' : btn.attr('id'),
                        },
                        headers: {
                            {!! __html::token_header() !!}
                        },
                    })
                        .then(response => {
                            $("#"+response.element_id).removeClass('active');
                            $("#"+response.element_id).html('<span class="text-red">'+response.time+'</span>');
                        })
                        .catch(error => {
                            console.log(error);
                            Swal.showValidationMessage(
                                'Error : '+ error.responseJSON.message,
                            )
                        })
                },
                allowOutsideClick: false,
                didClose : function () {
                    $(".editable-dtr").each(function () {
                        $(this).removeClass('active');
                    });
                },
            }).then((result) => {
                $(".editable-dtr").each(function () {
                    $(this).removeClass('active');
                });
                if (result.isConfirmed) {
                    notify('Time record successfully updated.');
                }
            })
        });


        $(".editable-remarks").click(function () {
            let btn = $(this);
            let date = btn.attr('data');
            btn.addClass('active');

            Swal.fire({
                title: 'Edit remarks:',
                inputAttributes: {
                    autocapitalize: 'off'
                },
                html : 'Max of 10 characters.<br><b class="text-danger">THIS ACTION CANNOT BE UNDONE</b>',
                input : 'text',
                backdrop : true,
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-check"></i> Save Changes',
                showLoaderOnConfirm: true,
                preConfirm: (text) => {
                    return $.ajax({
                        url : '{{route('dashboard.dtr.update_remarks')}}',
                        type: 'POST',
                        data: {
                            'date':date,
                            'biometric_user_id':'{{$bm_u_id}}',
                            'employee_no' : '{{$employee->employee_no}}',
                            'remark' : text,
                            'element_id' : btn.attr('id'),
                        },
                        headers: {
                            {!! __html::token_header() !!}
                        },
                    })
                        .then(response => {
                            $("#"+response.element_id).removeClass('active');
                            $("#"+response.element_id).html('<span class="text-red">'+response.remark+'</span>');
                        })
                        .catch(error => {
                            console.log(error);
                            Swal.showValidationMessage(
                                'Error : '+ error.responseJSON.message,
                            )
                        })
                },
                allowOutsideClick: false,
                didClose : function () {
                    $(".editable-remarks").each(function () {
                        $(this).removeClass('active');
                    });
                }
            }).then((result) => {
                $(".editable-remarks").each(function () {
                    $(this).removeClass('active');
                });
                if (result.isConfirmed) {
                    notify('Time record successfully updated.');
                }
            })
        });


        $(".editable-lt-ut").click(function () {
            let btn = $(this);
            let date = btn.attr('data');
            btn.addClass('active');

            Swal.fire({
                title: 'Enter Time:',
                html: '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Note:<br></span>' +
                    'An edited time record must be supported with a Permission Slip or Timekeeping Slip upon submission to the HR. ' +
                    '<br><br><b class="text-danger">PLEASE ENTER UNIT IN MINUTES BELOW</b><br><input id="lt_ut_input" value="'+btn.attr('val')+'" type="number" class="form-control time_input_dtr" autofocus>',
                inputAttributes: {
                    autocapitalize: 'off'
                },
                backdrop : true,
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-check"></i> Save Changes',
                showLoaderOnConfirm: true,
                preConfirm: (login) => {
                    return $.ajax({
                        url : '{{route('dashboard.dtr.update_lt_ut')}}',
                        type: 'POST',
                        data: {
                            'id':btn.attr('data'),
                            'time' : $("#lt_ut_input").val(),
                            'type' : btn.attr('data-type'),
                            'element_id' : btn.attr('id'),
                        },
                        headers: {
                            {!! __html::token_header() !!}
                        },
                        success: function (res) {
                            console.log(res);
                        }
                    })
                        .then(response => {
                            $("#"+response.element_id).removeClass('active');
                            $("#"+response.element_id).html('<span class="text-red">'+response.time+'</span>');
                        })
                        .catch(error => {
                            console.log(error);
                            Swal.showValidationMessage(
                                'Error : '+ error.responseJSON.message,
                            )
                        })
                },
                allowOutsideClick: false,
                didClose : function () {
                    $(".editable-dtr").each(function () {
                        $(this).removeClass('active');
                    });
                },
            }).then((result) => {
                $(".editable-dtr").each(function () {
                    $(this).removeClass('active');
                });
                if (result.isConfirmed) {
                    notify('Time record successfully updated.');
                }
            })
        });


    @endif

        $('body').on('click','.swal2-cancel',function () {

            $(".editable-dtr").each(function () {
                $(this).removeClass('active');
            })
            $(".editable-remarks").each(function () {
                $(this).removeClass('active');
            });

            $(".editable-lt-ut").each(function () {
                $(this).removeClass('active');
            });
        })
    </script>
    @if(empty($ud))
    <script>
        introJs().setOptions({
            steps: [
                {
                    intro: "<h4 class='no-margin'>Hi there!</h4>" +
                        " <br> The next steps will show you how to <b>EDIT</b> your DTR.<br><br>" +
                        " Please be reminded that edited time records must be supported with a Permission Slip (PS) or Timekeeping Slip upon submission of the DTR."
                }, {
                    element: document.querySelector('.first-empty-cell > .editable-dtr'),
                    intro: "Click here to edit time record."
                },{
                    element: document.querySelector('.first-empty-cell > .editable-remarks'),
                    intro: "You can also add your remarks by clicking here.<br><br> Editable cells are indicated with a light yellow background."
                },
            ],
            showProgress: true,
            exitOnOverlayClick: false,
        }).onbeforeexit(function () {
            $.ajax({
                url : '{{route("dashboard.ajax.post","dtr_edit_intro")}}',
                type: 'POST',
                headers: {
                    {!! __html::token_header() !!}
                },
                success: function (res) {
                    console.log(res);
                },
                error: function (res) {
                    console.log(res);
                }
            })
        }).start();
    </script>
    @endif
@endsection

