<?php


namespace App\Http\Controllers\Budget;


use App\Exports\BudgetProposalMonitoringExporter;
use App\Exports\StatementOfBudgeAndActualExpendituresExporter;
use App\Exports\SubsidiaryLedgerExporter;
use App\Exports\SummaryOfOrsExporter;
use App\Exports\SummaryOfOrsWithProjectsExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\ORSFormRequest;
use App\Models\Budget\ChartOfAccounts;
use App\Models\Budget\ORS;
use App\Models\Budget\ORSAccountEntries;
use App\Models\Budget\ORSProjectsApplied;
use App\Models\PPU\Pap;
use App\Models\PPU\RCDesc;
use App\Swep\Helpers\Arrays;
use App\Swep\Helpers\Get;
use App\Swep\Helpers\Helper;
use App\Swep\Services\Budget\ORSService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class ORSController extends Controller
{
    protected $orsService;
    public function __construct(ORSService $orsService)
    {
        $this->orsService = $orsService;
    }

    public function export_from_view(){
        return Excel::download(
            new StatementOfBudgeAndActualExpendituresExporter(),
            'test.xlsx',
        );
    }

    public function index(Request $request){
        if($request->ajax() && $request->has('draw')){
            return $this->dataTable($request);
        }
        return view('dashboard.budget.ors.index');
    }


    public function dataTable(Request $request){
        $ors = ORS::query()
            ->with(['accountEntries.chartOfAccount','projectsApplied.pap']);
        if($request->has('funds') && $request->funds != ''){
            $ors = $ors->where('funds','=',$request->funds);
        }
        if($request->has('ref_book') && $request->ref_book != ''){
            $ors = $ors->where('ref_book','=',$request->ref_book);
        }

        if($request->has('payee') && $request->payee != ''){
            $ors = $ors->where('payee','=',$request->payee);
        }

        if($request->has('applied_projects') && $request->applied_projects != ''){
            $ors = $ors->whereHas('projectsApplied',function ($q) use($request){
                return $q->where('pap_code','=',$request->applied_projects);
            });
        }

        return DataTables::of($ors)
            ->addColumn('action',function($data){
                return view('dashboard.budget.ors.dtActions')->with([
                    'data' => $data,
                ]);
            })
            ->addColumn('details',function($data) use($request){
                return view('dashboard.budget.ors.dtDetails')->with([
                    'data' => $data,
                ])->with([
                    'request' => $request,
                ]);
            })
            ->editColumn('amount',function($data){
                return number_format($data->amount,2);
            })
            ->editColumn('ors_date',function($data){
                return $data->ors_date != null ? Carbon::parse($data->ors_date)->format('M. d, Y') : '';
            })
            ->editColumn('payee',function($data){
                return view('dashboard.budget.ors.dtPayee')->with([
                    'data' => $data,
                ]);
            })
            ->addColumn('account_entries',function($data){
                return view('dashboard.budget.ors.dtAccountEntries')->with([
                    'data' => $data,
                ]);
            })
            ->escapeColumns([])
            ->setRowId('slug')
            ->toJson();
    }

    public function create(){
        return view('dashboard.budget.ors.create');
    }

    public function store(ORSFormRequest $request){
        $ors = new ORS;
        $ors->slug = Str::random();
//        $ors->ors_no = $this->orsService->newOrsNumber($request->funds)['newOrsNumber'];
//        $ors->base_ors_no = $this->orsService->newOrsNumber($request->funds)['baseOrsNumber'];
        $ors->ors_no = $request->ors_no;
        $ors->base_ors_no = substr($request->ors_no, strrpos($request->ors_no, '-' )+1);
        $ors->ors_date = $request->ors_date;
        $ors->funds = $request->funds;
        $ors->payee = $request->payee;
        $ors->office = $request->office;
        $ors->address = $request->address;
        $ors->ref_book = $request->ref_book;
        $ors->ref_doc = $request->ref_doc;
        $ors->amount = Helper::sanitizeAutonum($request->amount);
        $ors->particulars = $request->particulars;
        $ors->certified_by = $request->certified_by;
        $ors->certified_by_position = $request->certified_by_position;
        $ors->certified_budget_by = $request->certified_budget_by;
        $ors->certified_budget_by_position = $request->certified_budget_by_position;

        $rcs = Arrays::respCodeList();
        if(!empty($request->account_entries)){
            $arr = [];
            $ct = 0;
            foreach ($request->account_entries as $account_entry){
                array_push($arr,[
                    'slug' => Str::random(),
                    'ors_slug' => $ors->slug,
                    'type' => $account_entry['type'],
                    'seq_no' => $ct++,
                    'resp_center' => $account_entry['resp_center'],
                    'dept' => $rcs[$account_entry['resp_center']]['dept_alias'],
                    'unit' => $rcs[$account_entry['resp_center']]['sec'] == '' ? $rcs[$account_entry['resp_center']]['div'] : $rcs[$account_entry['resp_center']]['sec'],
                    'account_code' => $account_entry['account_code'],
                    'debit' => Helper::sanitizeAutonum($account_entry['debit']),
                    'credit' => Helper::sanitizeAutonum($account_entry['credit']),
                ]);
            }
            ORSAccountEntries::insert($arr);
        }

        if(!empty($request->applied_projects)){
            $arr = [];
            foreach ($request->applied_projects as $applied_project){
                array_push($arr,[

                    'slug' => Str::random(),
                    'ors_slug' => $ors->slug,
                    'pap_code' => $applied_project['pap_code'],
                    'co' => Helper::sanitizeAutonum($applied_project['co']),
                    'mooe' => Helper::sanitizeAutonum($applied_project['mooe']),
                    'total' => Helper::sanitizeAutonum($applied_project['co'] ?? 0) + Helper::sanitizeAutonum($applied_project['mooe'] ?? 0),
                ]);
            }
            ORSProjectsApplied::insert($arr);
        }
        if($ors->save()){
            return $ors->only('slug');
        }
        abort(503,'Error saving ORS');
    }

    public function print($slug, Request $request){

        if($request->has('attachment') && $request->attachment != null){
            return view('printables.ors.ors_attachment')->with([
                'ors' => $this->orsService->findBySlug($slug),
            ]);
        }
        return view('printables.ors.ors')->with([
            'ors' => $this->orsService->findBySlug($slug),
        ]);
    }

    public function edit($slug){
        return view('dashboard.budget.ors.edit')->with([
            'ors' => $this->orsService->findBySlug($slug),
        ]);
    }

    public function update(ORSFormRequest $request,$slug){

        $ors = $this->orsService->findBySlug($slug);
        $ors->ors_date = $request->ors_date;
        $ors->ors_no = $request->ors_no;
        $ors->funds = $request->funds;
        $ors->payee = $request->payee;
        $ors->office = $request->office;
        $ors->address = $request->address;
        $ors->ref_book = $request->ref_book;
        $ors->ref_doc = $request->ref_doc;
        $ors->amount = Helper::sanitizeAutonum($request->amount);
        $ors->particulars = $request->particulars;
        $ors->certified_by = $request->certified_by;
        $ors->certified_by_position = $request->certified_by_position;
        $ors->certified_budget_by = $request->certified_budget_by;
        $ors->certified_budget_by_position = $request->certified_budget_by_position;
        if($ors->update()){
            $rcs = Arrays::respCodeList();
            if(!empty($request->account_entries)){
                $arr = [];
                $ct = 0;
                foreach ($request->account_entries as $account_entry){
                    array_push($arr,[
                        'slug' => Str::random(),
                        'ors_slug' => $ors->slug,
                        'type' => $account_entry['type'],
                        'seq_no' => $ct++,
                        'resp_center' => $account_entry['resp_center'],
                        'dept' => $rcs[$account_entry['resp_center']]['dept_alias'],
                        'unit' => $rcs[$account_entry['resp_center']]['sec'] == '' ? $rcs[$account_entry['resp_center']]['div'] : $rcs[$account_entry['resp_center']]['sec'],
                        'account_code' => $account_entry['account_code'],
                        'debit' => Helper::sanitizeAutonum($account_entry['debit']),
                        'credit' => Helper::sanitizeAutonum($account_entry['credit']),
                    ]);
                }

                $ors->accountEntries()->delete();
                ORSAccountEntries::insert($arr);
            }

            if(!empty($request->applied_projects)){
                $arr = [];
                foreach ($request->applied_projects as $applied_project){
                    array_push($arr,[

                        'slug' => Str::random(),
                        'ors_slug' => $ors->slug,
                        'pap_code' => $applied_project['pap_code'],
                        'co' => Helper::sanitizeAutonum($applied_project['co']),
                        'mooe' => Helper::sanitizeAutonum($applied_project['mooe']),
                        'total' => Helper::sanitizeAutonum($applied_project['co'] ?? 0) + Helper::sanitizeAutonum($applied_project['mooe'] ?? 0),
                    ]);
                }
                $ors->projectsApplied()->delete();
                ORSProjectsApplied::insert($arr);
            }
            return $ors->only('slug');
        }
    }

    public function show($slug){
        $ors = $this->orsService->findBySlug($slug);
        return view('dashboard.budget.ors.show')->with([
            'ors' => $ors,
        ]);
    }

    public function reports(){
        return view('dashboard.budget.ors.reports');
    }

    public function reportGenerate($type){
        $request = Request::capture();

        switch ($type){
            case 'summary_of_ors':
                if(empty($request->date_from) || empty($request->date_to)){
                    abort(504,'Please select a date range.');
                }
                $baseSql = ORS::query()
                    ->with('projectsApplied.pap.responsibilityCenter.description')
                    ->whereBetween('ors_date',[
                        $request->date_from, $request->date_to
                    ]);
                if(!empty($request->resp_center) && $request->resp_center != ''){
                    $baseSql = $baseSql->whereHas('projectsApplied.pap.responsibilityCenter.description',function ($q) use ($request){
                        $q->where('rc','=',$request->resp_center);
                    });
                }

                if(!empty($request->fund_source) && $request->fund_source != ''){
                    $baseSql = $baseSql->where('funds','=',$request->fund_source);
                }


                $baseSql = $baseSql->orderBy('ors_no','asc');
                $ors = $baseSql->get();

                $groupedProjects = ORSProjectsApplied::with([
                        'ors',
                        'pap' => function($query){
                            $query->groupBy('resp_center');
                        },
                        'pap.responsibilityCenter.description',
                    ])
                    ->whereHas('ors',function ($q) use ($request){
                        $q->whereBetween('ors_date',[
                            $request->date_from, $request->date_to
                        ]);
                    })
                    ->get();

                $groupedProjectsArray = [];
                $groupedProjectsArrayNull = [];
                foreach ($groupedProjects as $groupedProject){
                    if(!empty($groupedProject->pap->responsibilityCenter->description)){
                        $groupedProjectsArray[$groupedProject->pap->responsibilityCenter->description->rc] = $groupedProject->pap->responsibilityCenter->description->name;
                        $groupedProjectsArrayNull[$groupedProject->pap->responsibilityCenter->description->rc] = ['mooe' => null,'co' => null, 'total' => null];
                    }
                }

                $orsArray = [];
                foreach ($ors as $or){
                    $orsArray[$or->slug]['obj'] = $or;
                    $orsArray[$or->slug]['projectsApplied'] = $groupedProjectsArrayNull;
                    if(count($or->projectsApplied) > 0){
                        foreach ($or->projectsApplied as $projectApplied) {
                            if(!empty($projectApplied->pap->responsibilityCenter)){
                                $department = $projectApplied->pap->responsibilityCenter->description;

                                $orsArray[$or->slug]['projectsApplied'][$department->rc ?? 'N/A']['mooe'] = $orsArray[$or->slug]['projectsApplied'][$department->rc ?? 'N/A']['mooe'] + $projectApplied->mooe;
                                $orsArray[$or->slug]['projectsApplied'][$department->rc ?? 'N/A']['co'] = $orsArray[$or->slug]['projectsApplied'][$department->rc ?? 'N/A']['co'] + $projectApplied->co;
                                $orsArray[$or->slug]['projectsApplied'][$department->rc ?? 'N/A']['total'] = $orsArray[$or->slug]['projectsApplied'][$department->rc ?? 'N/A']['total'] + $projectApplied->total;
                            }
                        }
                    }
                }

                if($request->excel == true){
                    return Excel::download(
                        new SummaryOfOrsExporter($ors,$groupedProjectsArray,$groupedProjectsArrayNull,$orsArray),
                        'Summary of ORS.xlsx',
                    );
                }

                return view('printables.ors.reports.summary_of_ors')->with([
                    'ors' => $ors,
                    'groupedProjects' => $groupedProjectsArray,
                    'groupedProjectsNull' => $groupedProjectsArrayNull,
                    'orsArray' => $orsArray,
                ]);
                break;


            case 'summary_of_ors_with_projects':
                $request = Request::capture();
                $arr = [];
                $start = $request->date_from;
                $end = $request->date_to;
                $ors = ORS::query()
                    ->with(['orsEntries.responsibilityCenter','projectsApplied'])
                    ->whereBetween('ors_date',[
                        $start,$end
                    ]);

                if(!empty($request->funds)){
                    $funds = $request->funds;
                    $ors = $ors->where(function ($q) use ($funds){
                        foreach ($funds as $fund){
                            $q = $q->orWhere('funds','=',$fund);
                        }
                    });
                }

                $ors = $ors->orderBy('ors_date','asc')
                    ->get();

                if($request->showAllColumns == 1){
                    $colss = collect(Arrays::departmentList())->map(function ($data){
                        return null;
                    })->toArray();
                }else{
                    $cols = ORSAccountEntries::query()
                        ->with('responsibilityCenter')
                        ->where('type','=','ORS')
                        ->whereHas('ors',function ($q) use ($start,$end,$request){
                            $q = $q->whereBetween('ors_date',[
                                $start,$end
                            ]);
                            if(!empty($request->funds)){
                                $funds = $request->funds;
                                $q->where(function ($q) use ($funds){
                                    foreach ($funds as $fund){
                                        $q = $q->orWhere('funds','=',$fund);
                                    }
                                });
                            }
                            return $q;
                        })
                        ->groupBy('resp_center')->get();
                    $colss = [];

                    foreach ($cols as $col){
                        $colss[$col->responsibilityCenter->rc] = null;
                    }
                }
                ksort($colss);
                foreach ($ors as $or){
                    $arr[$or->slug]['obj'] = $or;
                    $arr[$or->slug]['accountEntries'] = $colss;
                    if(!empty($or->orsEntries)){
                        foreach ($or->orsEntries as $det){

                            $arr[$or->slug]['accountEntries'][$det->responsibilityCenter->rc] = $arr[$or->slug]['accountEntries'][$det->responsibilityCenter->rc] + $det->debit;
                        }
                    }

                    if(!empty($or->projectsApplied)){
                        foreach ($or->projectsApplied as $proj){
                            $arr[$or->slug]['projectsApplied'][\Illuminate\Support\Str::random()] = $proj;
                        }
                    }
                }

                $departmentListAbbv = Arrays::departmentListAbbv();
                if($request->excel == true){
                    return Excel::download(
                        new SummaryOfOrsWithProjectsExporter($arr,$colss,$departmentListAbbv),
                        'Summary of ORS with Projects.xlsx',
                    );
                }

                return view('printables.ors.reports.summary_of_ors_with_projects')->with([
                    'burs' => $arr,
                    'cols' => $colss,
                    'departmentListAbbv' => $departmentListAbbv,
                ]);


                break;

            case 'quarterly_budget_monitoring':
                if(!$request->has('quarter') || $request->quarter == '' || !$request->has('year') || $request->year == ''){
                    abort(504,'Required fields: YEAR, QUARTER');
                }
                $appliedProjects = ORSProjectsApplied::query()->with(['pap.responsibilityCenter.description','ors']);
                if($request->has('resp_center') & $request->resp_center != ''){
                    $appliedProjects = $appliedProjects->whereHas('pap.responsibilityCenter.description',function ($q) use ($request){
                        return $q->where('rc','=',$request->resp_center);
                    });
                }
                if($request->has('fund_source') & $request->fund_source != ''){
                    $appliedProjects = $appliedProjects->whereHas('ors',function ($q) use ($request){
                        return $q->where('funds','=',$request->fund_source);
                    });
                }
                if($request->has('quarter') & $request->quarter != ''){
                    $year = $request->year;
                    $quarter = $request->quarter;
                    $appliedProjects = $appliedProjects->whereHas('ors',function ($q) use ($quarter, $year){
                       return $q->whereBetween('ors_date',[
                           Get::startAndEndOfQuarter($quarter, $year)['startOfQuarter'],
                           Get::startAndEndOfQuarter($quarter, $year)['endOfQuarter'],
                       ]);
                    });
                }
                $appliedProjects = $appliedProjects->get();
                $orsArray = [];

                foreach ($appliedProjects as $appliedProject){
                    $department = $appliedProject->pap->responsibilityCenter->description->rc ?? '';
                    $respCenter = $appliedProject->pap->responsibilityCenter->rc_code ?? '';
                    $papCode = $appliedProject->pap->pap_code ?? '';
                    $ors = $appliedProject->ors;

                    $orsArray[$department]['dept_obj'] = $appliedProject->pap->responsibilityCenter->description ?? null;
                    $orsArray[$department]['resp_centers'][$respCenter]['resp_center_obj'] = $appliedProject->pap->responsibilityCenter ?? null;
                    $orsArray[$department]['resp_centers'][$respCenter]['paps'][$papCode]['pap_obj'] = $appliedProject->pap;
                    $orsArray[$department]['resp_centers'][$respCenter]['paps'][$papCode]['ors'][$ors->slug ?? '']['ors_obj'] = $ors;
                    $orsArray[$department]['resp_centers'][$respCenter]['paps'][$papCode]['ors'][$ors->slug ?? '']['months'] = Helper::quarters()[$quarter];
                    $orsArray[$department]['resp_centers'][$respCenter]['paps'][$papCode]['ors'][$ors->slug ?? '']['months'][Carbon::parse($ors->ors_date)->format('m')] = $appliedProject;
                    $orsArray[$department]['resp_centers'][$respCenter]['paps'][$papCode]['ors'][$ors->slug ?? '']['applied_project_obj'] = $appliedProject;

                }
                Helper::ksortRecursive($orsArray);



                return view('printables.ors.reports.quarterly_budget_monitoring')->with([
                    'orsArray' => $orsArray,
                    'quarter' => $quarter,
                ]);
                break;
            case 'statement_of_budget_and_actual_expenditures':
                $groupedCoas = ChartOfAccounts::query()->get(['slug','account_code','account_title','bur_oblig']);
                $groupedCoasArray = [];
                if($request->showAllColumns == 1){
                    $depts = Arrays::deptsAssoc();
                }

                foreach ($groupedCoas as $coa){
                    $groupedCoasArray[$coa->bur_oblig][$coa->slug]['obj'] = $coa;
                }
                krsort($groupedCoasArray);
                $orsEntries = ORSAccountEntries::query()
                    ->select(DB::raw('*, sum(debit) as sum_debit, sum(credit) as sum_credit'))
                    ->where('type','=','ORS')
                    ->with(['ors' ,'chartOfAccount'])
                    ->groupBy(['account_code','resp_center']);
                if(!empty($request->date_from) && $request->date_from != ''){
                    $orsEntries->whereHas('ors',function ($q) use ($request){
                        return $q->whereBetween('ors_date',[$request->date_from,$request->date_to]);
                    });
                }
                $orsEntries = $orsEntries->get();
                foreach ($orsEntries as $orsEntry){
                    if(!empty($orsEntry->chartOfAccount)){
                        $coa = $orsEntry->chartOfAccount;

                        $groupedCoasArray[$coa->bur_oblig][$coa->slug]['resp_center'][$orsEntry->responsibilityCenter->description->name ?? null] = [
                            'sum_debit' => $orsEntry->sum_debit,
                            'sum_credit' => $orsEntry->sum_credit,
                        ];

                        if(!isset($depts[$orsEntry->responsibilityCenter->description->name ?? null])){
                            $depts[$orsEntry->responsibilityCenter->description->name ?? null] = null;
                        }

                    }
                }

                if($request->excel == true){
                    return Excel::download(
                        new StatementOfBudgeAndActualExpendituresExporter($depts,$groupedCoasArray),
                        'Statement of Budget and Actual Expenditures.xlsx',
                    );
                }

                return view('printables.ors.reports.statement_of_budget_and_actual_expenditures')->with([
                    'groupedCoasArray' => $groupedCoasArray,
                    'depts' => $depts,
                    'request' => $request,
                ]);
                break;

            case 'subsidiary_ledger':
                if(empty($request->account)){
                    abort(504,'Please select an account code.');
                }
                $ors = ORS::query()
                    ->with(['orsEntries.chartOfAccount','orsEntries.responsibilityCenter'])
                    ->whereHas('orsEntries.chartOfAccount',function ($q) use ($request){
                        return $q->where('account_code','=',$request->account);
                    });
                if(!empty($request->date_from) && !empty($request->date_to)){
                    $ors = $ors->whereBetween('ors_date',[$request->date_from,$request->date_to]);
                }else{
                    abort(504,'Please select date range.');
                }

                if(!empty($request->dept)){
                    $ors = $ors->whereHas('orsEntries.responsibilityCenter',function ($qq) use ($request){
                        return $qq->where('rc','=',$request->dept);
                    });
                }
                $ors = $ors->get();
                $account = ChartOfAccounts::query()->where('account_code','=',$request->account)->first();
                if(empty($account)){
                    abort(504,'Account Code does not exist.');
                }
                if($request->excel == true){
                    return Excel::download(
                        new SubsidiaryLedgerExporter($ors,$account,$request),
                        'Subsidiary Ledger.xlsx',
                    );
                }

                return view('printables.ors.reports.subsidiary_ledger')->with([
                    'ors' => $ors,
                    'account' => $account,
                    'request' => $request,
                ]);
                break;

            case 'budget_proposal_monitoring':
                if(empty($request->dept)){
                    abort(504,'Please select department');
                }
                $papsArray = Pap::query()
                    ->with(['responsibilityCenter']);
                if(!empty($request->dept)){
                    $papsArray = $papsArray->whereHas('responsibilityCenter',function ($q) use ($request){
                        return $q->where('rc','=',$request->dept);
                    });
                }
                $papsArray = $papsArray->get();
                $papsArray  = $papsArray->pluck(null,'pap_code')->map(function ($data){
                    return [
                        'utilized' => null,
                        'pap' => $data->toArray(),
                    ];
                })->toArray();

                $paps = Pap::query()
                    ->with(['responsibilityCenter','orsAppliedProjects.ors'])
                    ->orderBy('pap_code','asc');
                if(empty($request->date_from) || empty($request->date_to)){
                    abort(504,'Please select date range');
                }else{
                    $paps = $paps->whereHas('orsAppliedProjects.ors',function ($q) use ($request){
                        return $q->whereBetween('ors_date',[$request->date_from,$request->date_to]);
                    });
                }

                //IF USER HAS CHOSEN A DEPARTMENT
                if(!empty($request->dept)){
                    $paps = $paps->whereHas('responsibilityCenter',function ($q) use ($request){
                        return $q->where('rc','=',$request->dept);
                    });
                }
                $paps = $paps->get();
                foreach ($paps as $pap){
                    $papsArray[$pap->pap_code]['utilized'] = $pap->orsAppliedProjects->sum('total');
                }

                //IF USER REQUESTS FOR EXCEL
                if($request->excel == true){
                    return Excel::download(
                        new BudgetProposalMonitoringExporter($papsArray,$request),
                        'Budget Proposal - Monitoring.xlsx',
                        );
                }
                return view('printables.ors.reports.budget_proposal_monitoring')->with([
                    'paps' => $papsArray,
                    'request' => $request,
                ]);
                break;
            default:
                return 'default';
                break;
        }
    }



    public function destroy($slug){
        $ors = $this->orsService->findBySlug($slug);
        if($ors->delete()){
            $ors->accountEntries()->delete();
            $ors->projectsApplied()->delete();
            return 1;
        }
        abort(503,'Error deleting ORS.');
    }
}