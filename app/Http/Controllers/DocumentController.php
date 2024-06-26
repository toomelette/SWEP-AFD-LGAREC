<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Swep\Helpers\__static;
use App\Swep\Repositories\DocumentRepository;
use App\Swep\Services\DocumentService;
use App\Http\Requests\Document\DocumentFormRequest;
use App\Http\Requests\Document\DocumentFilterRequest;
use App\Http\Requests\Document\DocumentDownloadRequest;
use App\Http\Requests\Document\DocumentDisseminationRequest;
use Howtomakeaturn\PDFInfo\PDFInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Activitylog\Models\Activity;

class DocumentController extends Controller{



	protected $document;


    public function __construct(DocumentService $document){
        $this->document = $document;


    }



    
    public function index(DocumentFilterRequest $request){

//        Auth::user()->hasAccessToDocuments('QC','VIS');

        $documents = Document::with(['folder','folder2']);

        if ($request->ajax() && !empty($request->draw)){
//            return 1;
            return $this->dataTable($request, $documents);
        }

        return view('dashboard.document.index');

    }

    
    public function dataTable($request, $documents){

        if(!empty($request->type)){
            $documents->where('type','=',$request->type);
        }
        if(!empty($request->person_to)){
            $documents->where('person_to','=',$request->person_to);
        }
        if(!empty($request->person_from)){
            $documents->where('person_from','=',$request->person_from);
        }
        if(!empty($request->folder_code)){
            $documents->where('folder_code','=',$request->folder_code);
        }

        if(!empty($request->date_before)){
            $documents->where('date','<=',$request->date_before);
        }
        if(!empty($request->date_after)){
            $documents->where('date','>=',$request->date_after);
        }

        $storage = $this->getStorage();

        return \DataTables::of($documents)
            ->addColumn('view_document',function($data) use ($storage){
                if($storage->exists($data->path.$data->filename)){
                    return '<a href="'.route("dashboard.document.view_file", $data->slug).'" class="btn btn-sm btn-success" target="_blank">
                                    <i class="fa fa-file-o"></i>
                                  </a>';
                }else{
                    return '<button class="btn btn-sm btn-warning" title="File not found" disabled><i class="fa fa-exclamation-circle" ></i></button>';
                }
            })
            ->addColumn('action',function($data){
                $destroy_route = "'".route("dashboard.document.destroy","slug")."'";
                $slug = "'".$data->slug."'";
                $button = '<div class="btn-group">
                                    <button type="button" class="btn btn-default btn-sm view_document_btn" data="'.$data->slug.'" data-toggle="modal" data-target ="#show_document_modal" title="View more" data-placement="left">
                                        <i class="fa fa-file-text"></i>
                                    </button>
                                   
                                    <button  data-toggle="modal" data-target="#edit_document_modal" for="linkToEdit" type="button" data="'.$data->slug.'" class="btn btn-default btn-sm edit_document_btn"  title="Edit" data-placement="top">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <button type="button" data="'.$data->slug.'" onclick="delete_data('.$slug.','.$destroy_route.')" class="btn btn-sm btn-danger delete_jo_employee_btn" data-toggle="tooltip" title="Delete" data-placement="top">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                   <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                          <span class="caret"></span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-right">
                                          <li><a href="'.route('dashboard.document.dissemination', $data->slug).'" target="_blank" class="service_records_btn" data="'.$data->slug.'"><i class="fa icon-service-record"></i> Disseminate</a></li>
                                          <li><a href="'.route('dashboard.document.dissemination', $data->slug).'?send_copy=1" target="_blank" class="trainings_btn" data="'.$data->slug.'"><i class="fa icon-seminar"></i> Send Copy</a></li>
                                          <li><a href="#" data-toggle="modal" data-target="#matrix_modal" class="print_qr_btn" data="'.$data->slug.'"><i class="fa fa-dashboard"></i>Print QR</a></li>
                                         </ul>
                                    </div>
                                </div>';
                return $button;
            })
            ->editColumn('date',function($data){
                return Carbon::parse($data->date)->format('m/d/Y');
            })
            ->editColumn('reference_no',function($data){
                $one = '<i class="fa fa-folder"></i> '.$data->folder_code;
                if(!empty($data->folder)){
                    $one = '<a title="'.$data->folder->description.'" href="'.route("dashboard.document_folder.browse",$data->folder_code).'" target="_blank"><i class="fa fa-folder"></i> '.$data->folder_code.'</a>';
                }
                if($data->folder_code2 == ''){
                    $two = '';
                }else{
                    if(!empty($data->folder2)){
                        $two = ' & <a title="'.$data->folder2->description.'" href="'.route("dashboard.document_folder.browse",$data->folder_code2).'" target="_blank">'.$data->folder_code2.'</a>';
                    }else{
                        $two = ' & '. $data->folder_code2;
                    }
                }
                $folder_sub = '<span class="pull-right">'.$one.$two.'</span>';
                return $data->reference_no.'
                    <div class="table-subdetail" style="margin-top: 3px">
                         '.array_search($data->type,__static::document_types()).$folder_sub.'
                    </div>';
            })
            ->editColumn('subject',function($data){
                return view('dashboard.document.dtSubject')->with([
                    'data' => $data,
                ]);
            })
            ->escapeColumns([])
            ->setRowId('slug')
            ->toJson();
    }

    public function create(){
        return view('dashboard.document.create');
    }

    private function getStorage(){
        if( Auth::user()->getAccessToDocuments() == 'QC'){
            return Storage::disk('qc');
        }elseif(  Auth::user()->getAccessToDocuments() == 'VIS'){
            return Storage::disk('local');
        }


//        if(Auth::user()->access == 'VIS' ||Auth::user()->access == 'LGAREC'){
//
//        }elseif (Auth::user()->access == 'LM' || Auth::user()->access == 'QC'){
//
//        }
    }


    public function store(DocumentFormRequest $request, DocumentRepository $documentRepository){
        $path = Carbon::parse($request->date)->format('Y').'/'.$request->folder_code.'/';
        $path2 = null;
        if(!empty($request->folder_code2)){
            $path2 = Carbon::parse($request->date)->format('Y').'/'.$request->folder_code2.'/';
        }

        $storage = $this->getStorage();

        $document_id = $documentRepository->getDocumentIdInc();

        $new_file_name = $request->reference_no.'.'.$request->file('doc_file')->getClientOriginalExtension();
        $document = new Document;
        $document->visibility = Auth::user()->access;
        $document->slug = Str::random();
        $document->reference_no = strtoupper($request->reference_no);
        $document->date = Carbon::parse($request->date)->format('Y-m-d');
        $document->person_from = $request->person_from;
        $document->person_to = $request->person_to;
        $document->type = $request->type;
        $document->subject = $request->subject;
        $document->path = $path;
        $document->filename = $new_file_name;
        $document->document_id = $document_id;
        $document->folder_code = $request->folder_code;
        $document->folder_code2 = $request->folder_code2;
        $document->remarks = $request->remarks;
        $document->qr_location = $request->qr_location;

        if(!empty($request->qr_location)){
            //Make QR
            $this->makeQR($document,$document_id);
            $image1 = $storage->path('/QRCODE_TEMP/'.$document_id.'.png');
            //Processed PDF
            $output = $this->stampPDFwithQR($request,$image1,$document_id);
        }else{
            $output = $request->file('doc_file')->get();
        }

        //Write to Disk
        $storage->put($path.'/'.$new_file_name,$output);
        //Cross filing
        if(!empty($request->folder_code2)){
            $document->path2 = $path2;
            $storage->put($path2.'/'.$new_file_name,$output);
        }

        //Delete Temporary QR
        $storage->delete('/QRCODE_TEMP/'.$document_id.'.png');

        if($document->save()){
            return $document->only('slug');
        }
        abort(503,'Error saving data');
        return $this->document->store($request);
    }

    public  function makeQR($document,$document_id){
        //Make QR Code
        $image = QrCode::size('200')
            ->format('png')
            ->merge('/public/images/sra_only2.png',0.4)
            ->errorCorrection('H')
            ->generate(route("dashboard.document.view_file",$document->reference_no).'?trigger=SCANNER');
        //Store QR Code temporarily
        $this->getStorage()->put('/QRCODE_TEMP/'.$document_id.'.png',$image);
    }

    private function stampPDFwithQR($request,$image1,$document_id){
        $pdf = new \setasign\Fpdi\Fpdi();

        $totalPages = $pdf->setSourceFile($request->file('doc_file')->path());
        for ($pageNo = 1;$pageNo <= $totalPages; $pageNo++){
            $pdf->AddPage();
            $tplIdx = $pdf->importPage($pageNo);
            $page_height = $pdf->getTemplateSize($tplIdx)['height'];
            $page_width = $pdf->getTemplateSize($tplIdx)['width'];
            $mainX = $this->getXY($request->qr_location,$page_width,$page_height)['mainX'];
            $mainY = $this->getXY($request->qr_location,$page_width,$page_height)['mainY'];
            $pdf->useTemplate($tplIdx, 0, 0, null, null, true);
            $pdf->SetXY($mainX,$mainY);
            if($pageNo < 2){

                $pdf->SetFont('Arial', '', '8');
                $pdf->Image($image1,$mainX-20,$mainY-15,15 , 15);
                $pdf->SetFont('Arial', '', '8');
                $pdf->SetXY($mainX-5,$mainY-7);
                $pdf->Multicell(60,2    ,$document_id,0,"L");
                $pdf->SetXY($mainX-5,$mainY-15);
                $pdf->SetFont('Arial', '', '6');
                $pdf->Multicell(60,2    ,"SUGAR REGULATORY ADMINISTRATION\nRECORDS SECTION\nDOCUMENT ARCHIVING SYSTEM",0,"L");

            }

        }
        return  $output = $pdf->Output('S');
    }
    private  function getXY($location,$page_width, $page_height){
        $mainX = $page_width - 50;
        $mainY = 20;

        switch ($location){
            case 'UPPER_RIGHT':
                $mainX = $page_width - 50;
                $mainY = 20;
                break;
            case 'UPPER_LEFT':
                $mainX = 30;
                $mainY = 20;
                break;
            case 'LOWER_RIGHT':
                $mainX = $page_width  - 50;
                $mainY = $page_height - 19;
                break;
            case 'LOWER_LEFT':
                $mainX = 30;
                $mainY = $page_height - 19;
                break;
            case 'LOWER_LEFT_PADDED':
                $mainX = 90;
                $mainY = $page_height - 19;
                break;
        }

        return [
            'mainX' => $mainX,
            'mainY' => $mainY,
        ];
    }


    public function show($slug){
        $document = $this->findBySlug($slug);
        return view('dashboard.document.show')->with([
            'document' => $document
        ]);
        return $this->document->show($slug);
        
    }




    public function edit($slug){
        $document = $this->findBySlug($slug);
        return view('dashboard.document.edit')->with([
            'document' => $document
        ]);
        return $this->document->edit($slug);
        
    }




    public function update(DocumentFormRequest $request, $slug){
        $document = $this->findBySlug($slug);
        $document->reference_no = $request->reference_no;
        $document->date = Carbon::parse($request->date)->format('Y-m-d');
        $document->person_to = $request->person_to;
        $document->person_from = $request->person_from;
        $document->type = $request->type;
        $document->subject = $request->subject;
        $document->folder_code = $request->folder_code;
        $document->folder_code2 = $request->folder_code2;
        $document->remarks = $request->remarks;
        $document_year_folder = Carbon::parse($document->date)->format('Y');
        $request_year_folder = Carbon::parse($request->date)->format('Y');

        $new_filename = $document->filename;

        if($document->isDirty('date') || $document->isDirty('folder_code') || $document->isDirty('folder_code2') || $document->isDirty('reference_no')){
            $path = '';
            $path2 = '';

            if($document->isDirty('reference_no')){
                $new_filename = $request->reference_no.'.'.substr($document->filename, strrpos($document->filename, '.') + 1);

            }

            if($document_year_folder != $request_year_folder){
                $path = $request_year_folder.'/';
//                $path2 = $request_year_folder.'/';
            }else{
                $path = $document_year_folder.'/';
//                $path2 = $request_year_folder.'/';
            }

            if($document->isDirty('folder_code')){
                $path = $path.$request->folder_code.'/';
            }else{
                $path = $path.$document->folder_code.'/';
            }
            if($path != $document->path || $document->isDirty('reference_no')){
                $this->getStorage()->move($document->path.$document->filename,$path.$new_filename);
            }

            $document->path = $path;


            if(!empty($request->folder_code2)){
                if($document_year_folder != $request_year_folder){
                    $path2 = $request_year_folder.'/';
                }else{
                    $path2 = $request_year_folder.'/';
                }

                if($document->isDirty('folder_code2')){
                    $path2 = $path2.$request->folder_code2.'/';
                }else{
                    $path2 = $path2.$document->folder_code2.'/';
                }

                if($path2 != $document->path2 || $document->isDirty('reference_no')){
                    //IF PATH 2 IS CHANGED
                    if($this->getStorage()->exists($document->path2.$document->filename)){
                        $this->getStorage()->move($document->path2.$document->filename,$path2.$new_filename);
                    }else{
                        $this->getStorage()->copy($path.$new_filename,$path2.$new_filename);
                    }
                    $document->path2 = $path2;
                }
            }else{
                if($this->getStorage()->exists($document->path2.$new_filename)){
                    $this->getStorage()->delete($document->path2.$new_filename);
                }
                $document->path2 = null;
            }

        }
        $document->filename = $new_filename;
        if($document->update()){
            return $document->only('slug');
        }
        return 1;
        if($document->isDirty('folder_code')){

            return 'dirty';
        }else{
            return 'clean';
        }
        //$document->update();
        return $request;
        //return $this->document->update($request, $slug);

    }

    


    public function destroy($slug){
        $document = $this->findBySlug($slug);
        if($this->getStorage()->exists($document->path.$document->filename)){
            $this->getStorage()->move($document->path.$document->filename,$document->path.$document->filename.'.x');
           // $this->getStorage()->delete($document->path.$document->filename);
        }
        if($this->getStorage()->exists($document->path2.$document->filename)){
            $this->getStorage()->move($document->path2.$document->filename,$document->path2.$document->filename.'.x');
            //$this->getStorage()->delete($document->path2.$document->filename);
        }

        if($document->delete()){
            return 1;
        }
       return $this->document->destroy($slug); 

    }




    public function viewFile($slug){
        $request_slug = \Illuminate\Support\Facades\Request::get('slug');

        if(empty(\Illuminate\Support\Facades\Request::get('slug'))){
            $document = $this->findBySlug($slug);

            if(!empty($document)){
                $rt = route('dashboard.document.view_file',$document->reference_no).'?slug='.$slug;
                return redirect($rt);
            }
        }
        return $this->document->viewFile($request_slug);

    }

    public function findByRefNo($ref_no){
        $document = Document::query()->where('reference_no','=',$ref_no)->first();
        if(!empty($document)){
            return $document;
        }
        abort(503);
    }

    public function findBySlug($slug){
        $d = Document::query()->with(['folder','folder2'])->where('slug','=',$slug)->first();
        if(!empty($d)){
            return $d;
        }
        abort(503,'Document does not exits in the database.');
    }


    public function download(){
        $ys = [];
        $years = $this->document->getRaw()->groupBy('year')->orderBy('year','asc')->pluck('year'); 
        foreach ($years as $year) {
            $ys[$year] = $year;
        }
        return view('dashboard.document.download')->with(['years'=> $ys]);

    }




    public function downloadDirect(DocumentDownloadRequest $request, $slug){

       return $this->document->downloadDirect($request, $slug); 

    }




    public function dissemination(Request $request, $slug){

       return $this->document->dissemination($request,$slug); 

    }




    public function disseminationPost(DocumentDisseminationRequest $request, $slug){

       return $this->document->disseminationPost($request, $slug); 
       
    }

    public function print($slug)
    {
        return $this->document->print($slug); 
    }

    public function report(){
        return view('dashboard.document.report');
    }
    
    public function report_generate(Request $request){

        $activity = activity()
            ->performedOn(new Document())
            ->causedBy(Auth::user()->id)
            ->withProperties(['attributes' => 'Generated report on Document Dissemination.'])
            ->log('generated');

        return $this->document->report_generate($request);
    }

    public function rename_all(){
        //return 1;
        return $this->document->rename_all();
    }

    public function printQr($slug){

        $document = $this->findBySlug($slug);
        $storage = $this->getStorage();
        $this->makeQR($document,$document->document_id);
        $path = '/QRCODE_TEMP/'.$document->document_id.'.png';
        $image1 = $storage->path($path);

        return view('dashboard.document.print_qr')->with([
            'document' => $document,
        ]);
    }
    public function getQr($slug){
        //    $path = storage_path('public/' . $filename);

        if( Auth::user()->getAccessToDocuments() == 'QC'){
            $storage =  Storage::disk('qc');
        }elseif(  Auth::user()->getAccessToDocuments() == 'VIS'){
            $storage = Storage::disk('local');
        }
        $document = \App\Models\Document::query()->where('slug','=',$slug)->first();
        $path = '/QRCODE_TEMP/'.$document->document_id.'.png';
        if($storage->exists($path)){
            $file = $storage->get($path);
            $type = $storage->getMimetype($path);
            $response = \Response::make($file, 200);
            $response->header("Content-Type", $type);
            return $response;
        }
        abort(503,'FILE DOES NOT EXIST');


    }
}
