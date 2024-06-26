@php
$rand = \Illuminate\Support\Str::random();
@endphp
<tr  id="ap{{$rand}}">
    <td>
        {!! \App\Swep\ViewHelpers\__form2::selectOnly('applied_projects['.$rand.'][resp_center]',[
            'class' => 'input-sm resp_center_clear select2_respCenter applied_projects_'.$rand.'_resp_center',
            'options' => \App\Swep\Helpers\Arrays::groupedRespCodes(),
            'for' => 'resp_center',
            'container_class' => 'select2-sm',
        ],$data->pap->responsibilityCenter->rc_code ?? null) !!}
    </td>
    @if(request()->ajax())
        <td>
            {!! \App\Swep\ViewHelpers\__form2::selectOnly('applied_projects['.$rand.'][pap_code]',[
                'class' => 'input-sm select2_clear select2_pap_code_'.$rand.' applied_projects_'.$rand.'_pap_code',
                'container_class' => 'select2-sm',
                'options' => [],
                'id' => 'select2_id_ap'.$rand,
            ],$data->pap_code ?? null) !!}
        </td>
        <td>
            {!! \App\Swep\ViewHelpers\__form2::textboxOnly('applied_projects['.$rand.'][mooe]',[
                'class' => 'input-sm text-right autonum_'.$rand. ' applied_projects_'.$rand.'_mooe',
                'for' => 'mooe',
            ],$data->mooe ?? null) !!}
            <small class="text-balance">
                Balance: <span class="balance_mooe text-info pull-right"></span>
            </small>
        </td>
        <td>
            {!! \App\Swep\ViewHelpers\__form2::textboxOnly('applied_projects['.$rand.'][co]',[
                'class' => 'input-sm text-right autonum_'.$rand.' applied_projects_'.$rand.'_co',
                'for' => 'co',
            ],$data->co ?? null) !!}
            <small class="text-balance">
                Balance: <span class="balance_co text-info pull-right"></span>
            </small>
        </td>
        <td>
            <button class="btn btn-danger btn-sm remove_row_btn" type="button"><i class="fa fa-times"></i> </button>
        </td>
    @else
        <td>
            {!! \App\Swep\ViewHelpers\__form2::selectOnly('applied_projects['.$rand.'][pap_code]',[
                'class' => 'input-sm select2_clear select2_pap_code',
                'container_class' => 'select2-sm',
                'options' => [],
                'select2_preSelected' => (!empty($data->pap)) ? $data->pap_code.' | '.$data->pap->pap_title : '' ,
            ],$data->pap_code ?? null) !!}
        </td>
        <td>
            {!! \App\Swep\ViewHelpers\__form2::textboxOnly('applied_projects['.$rand.'][mooe]',[
                'class' => 'input-sm text-right autonum',
                'for' => 'mooe',
            ],($data->mooe == 0 || $data->mooe == null || $data->mooe == '') ? '' : $data->mooe) !!}
        </td>
        <td>
            {!! \App\Swep\ViewHelpers\__form2::textboxOnly('applied_projects['.$rand.'][co]',[
                'class' => 'input-sm text-right autonum',
                'for' => 'co',
            ],($data->co == 0 || $data->co == null || $data->co == '') ? '' : $data->co) !!}
        </td>
        <td>
            <button class="btn btn-danger btn-sm remove_row_btn" type="button"><i class="fa fa-times"></i> </button>
        </td>
    @endif
</tr>

<script type="text/javascript">
    $(".autonum_{{$rand}}").each(function(){
        new AutoNumeric(this, autonum_settings);
    });

    $(".select2_pap_code_{{$rand}}").select2({
        ajax: {
            url: function () {
                let baseUrl = "{{route('dashboard.ajax.get','pap')}}";
                let respCode = $(this).parents('tr').find('select[for="resp_center"]').val();
                return baseUrl+'?respCode='+respCode;
            },
        },
        placeholder: 'Select item',
    });
    $(".select2_respCenter").select2();
    $('.select2_pap_code_{{$rand}}').on('select2:select', function (e) {
        let t = $(this);
        let parentTrId = t.parents('tr').attr('id');
        let data = e.params.data;
        console.log(parentTrId);
        $("#"+parentTrId+" [for='account_code']").val(data.id);
        $.ajax({
            url : '{{route('dashboard.ajax.get','ors_pap_balances')}}',
            data: {
                slug : data.slug,
            },
            type : 'GET',
            success: function (res) {
                $("#"+parentTrId+" .balance_mooe").html($.number(res.balance.mooe,2));
                $("#"+parentTrId+" .balance_co").html($.number(res.balance.co,2));
            },
            error: function (res) {
                console.log(res);
            }
        })
    });
</script>