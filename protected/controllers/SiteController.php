<?php

class SiteController extends Controller
{
	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
			// page action renders "static" pages stored under 'protected/views/site/pages'
			// They can be accessed via: index.php?r=site/page&view=FileName
			'page'=>array(
				'class'=>'CViewAction',
			),
		);
	}

	/**
	 * This is the default 'index' action that is invoked
	 * when an action is not explicitly requested by users.
	 */
	public function actionIndex()
	{
		// renders the view file 'protected/views/site/index.php'
		// using the default layout 'protected/views/layouts/main.php'
		$this->render('index');
	}

	/**
	 * This is the action to handle external exceptions.
	 */
	public function actionError()
	{
		if($error=Yii::app()->errorHandler->error)
		{
			if(Yii::app()->request->isAjaxRequest)
				echo $error['message'];
			else
				$this->render('error', $error);
		}
	}
        
        private function searchCrossRef($query)
        {
            $url = "http://api.crossref.org/works?rows=1&query=" . urlencode($query);            
            $apiRequest = Yii::app()->curl->run($url);
            
            if($apiRequest->hasErrors()) {
                throw new CException('Gagal menghubungi server');
            } else {
                $apiResponse = CJSON::decode($apiRequest->getData());
                // CVarDumper::dump($apiResponse, 10, TRUE);
                if($apiResponse === null) {
                    throw new CException("Gagal parsing data");
                }
                if($apiResponse['message']['total-results'] == 0) {
                    throw new CException("Tidak ditemukan hasil");
                }
                
                $foundTitle = $apiResponse['message']['items'][0]['title'][0];
                $foundScore = $apiResponse['message']['items'][0]['score'];
                if($foundScore < 2.0 || StringHelper::stringSimilarity($query, $foundTitle) < 80) {
                    throw new CException("Tidak ditemukan hasil");
                }
                
                $refType = $apiResponse['message']['items'][0]['type'];
                switch($refType) {
                    case 'journal-article':
                        return $this->createJournal($apiResponse['message']['items'][0]);
                    case 'proceedings-article':
                        
                        break;
                    case 'book-chapter':
                        return $this->createBookChapter($apiResponse['message']['items'][0]);
                    default: 
                        throw new CException("Tidak ditemukan hasil");
                }
            }
        }
        
        private function searchBookData($isbn)
        {
            $isbn = StringHelper::getISBN($isbn);            
            $url = "http://xisbn.worldcat.org/webservices/xid/isbn/" . urlencode($isbn) . "?method=getMetadata&format=json&fl=*";
            
            $apiRequest = Yii::app()->curl->run($url);
            
            if($apiRequest->hasErrors()) {
                throw new CException('Gagal menghubungi server ISBN');
            } else {
                $apiResponse = CJSON::decode($apiRequest->getData());
                if($apiResponse === null) {
                    throw new CException("Gagal parsing data ISBN");
                }            
                if($apiResponse['stat'] !== 'ok') {
                    throw new CException("Tidak ditemukan hasil ISBN");
                }                
                
                return $apiResponse['list'][0];
            }
        }
        
        private function searchCityData($city)
        {
            $cities = Yii::app()->curl->run("http://gd.geobytes.com/AutoCompleteCity?q=" . urlencode($city));
            if(!$cities->hasErrors()) {
                $city = CJSON::decode($cities->getData())[0];
                $cityData = Yii::app()->curl->run("http://gd.geobytes.com/GetCityDetails?fqcn=" . urlencode($city));
                if(!$cityData->hasErrors()) {
                    $country = CJSON::decode($cityData->getData());
                    return $country['geobytesinternet'];
                }                
                return "";
            }
            return "";
        }
        
        private function createJournal($data)
        {
            $journal = new Journal();
            $journal->authors = isset($data['author']) ? $data['author'] : '[Anonim]';
            $journal->year    = isset($data['issued']['date-parts'][0][0]) ? $data['issued']['date-parts'][0][0] : '[Tahun tidak diketahui]';
            $journal->title   = isset($data['title'][0]) ? $data['title'][0] : '[Judul tidak diketahui]';
            $journal->volume  = isset($data['volume']) ? $data['volume'] : '[Volume tidak diketahui]';
            $journal->issue   = isset($data['issue']) ? $data['issue'] : '';
            $journal->pages   = isset($data['page']) ? $data['page'] : '[Halaman tidak diketahui]';
            $journal->doi     = isset($data['DOI']) ? $data['DOI'] : '';

            $journalNames = isset($data['container-title']) ? $data['container-title'] : array();
            $journalName = "";
            if(count($journalNames) >= 2) { // Jurnal dan singkatannya
                if(strlen($journalNames[0]) > strlen($journalNames[1]))
                    $journalName = $journalNames[1]; // Ambil singkatannya
                else
                    $journalName = $journalNames[0];
            } elseif(count($journalNames) == 1) {
                $journalName = $journalNames[0];
            } else {
                $journalName = '[Jurnal tidak diketahui]';
            }
            
            $journal->journal = $journalName;
            
            CVarDumper::dump($journal, 10, TRUE);
        }
        
        private function createBookChapter($data)
        {
            try {
                $bookData = $this->searchBookData($data['ISBN'][0]);
            } catch (CException $e) {
                $bookData = null;
            }
            
            $chapter = new BookChapter();
            $chapter->authors = isset($data['author']) ? $data['author'] : '[Anonim]';
            $chapter->year    = isset($data['issued']['date-parts'][0][0]) ? $data['issued']['date-parts'][0][0] : '[Tahun tidak diketahui]';
            $chapter->title   = isset($data['title'][0]) ? $data['title'][0] : '[Judul tidak diketahui]';            
            $chapter->pages   = isset($data['page']) ? $data['page'] : '[Halaman tidak diketahui]';
            
            if($bookData !== null) {
                $chapter->book_title = StringHelper::titleCase($bookData['title']);
                $chapter->pub        = $bookData['publisher'];
                $chapter->editors    = $bookData['author'];
                $chapter->pub_city   = $bookData['city']; 
                $chapter->pub_country= $this->searchCityData($chapter->pub_city);
            } else {
                $chapter->book_title = StringHelper::titleCase($data['container-title'][0]);
                $chapter->pub        = $data['publisher'];
            }
            
            CVarDumper::dump($chapter, 10, TRUE);
        }
        
        public function actionSearch()
        {
            $query = str_replace("\n", " ", $_POST['q']);
            $query = str_replace(":", "", $query);
            $query = preg_replace('!\s+!', ' ', $query);
            
            $crossRef = $this->searchCrossRef($query);
        }

}