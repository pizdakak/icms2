<?php

class actionUsersProfileContent extends cmsAction {

    public $lock_explicit_call = true;

    public function run($profile, $ctype_name = false, $folder_id = false, $dataset = false){

        if (!$ctype_name) { cmsCore::error404(); }

        $ctype = $this->controller_content->model->getContentTypeByName($ctype_name);
        if (!$ctype) { cmsCore::error404(); }

        if (!$this->cms_user->isPrivacyAllowed($profile, 'view_user_'.$ctype['name'])){
            cmsCore::error404();
        }

        if($folder_id && !$dataset && !is_numeric($folder_id)){
            $dataset   = $folder_id;
            $folder_id = false;
        }

        $this->controller_content->setListContext('profile_content');

        // Получаем список наборов
        $datasets = $this->controller_content->getCtypeDatasets($ctype, array(
            'cat_id' => 0
        ));

        $folders = array();

        if ($ctype['is_folders']){

            $folders = $this->controller_content->model->getContentFolders($ctype['id'], $profile['id']);

            if ($folders){
                if ($folder_id && array_key_exists($folder_id, $folders)){
                    $this->controller_content->model->filterEqual('folder_id', $folder_id);
                }
            }

        }

        // Если есть наборы, применяем фильтры текущего
        if ($datasets){

            if($dataset && empty($datasets[$dataset])){ cmsCore::error404(); }

            $keys = array_keys($datasets);
            $current_dataset = $dataset ? $datasets[$dataset] : $datasets[$keys[0]];
            $this->controller_content->model->applyDatasetFilters($current_dataset);
            // устанавливаем максимальное количество записей для набора, если задано
            if(!empty($current_dataset['max_count'])){
                $this->controller_content->max_items_count = $current_dataset['max_count'];
            }
            // если набор всего один, например для изменения сортировки по умолчанию,
            // не показываем его на сайте
            if(count($datasets) == 1){
                unset($current_dataset); $datasets = false;
            }

        }

        $this->controller_content->model->filterEqual('user_id', $profile['id']);

        list($folders, $this->controller_content->model, $profile, $folder_id) = cmsEventsManager::hook("user_content_{$ctype['name']}_folders", array(
            $folders,
            $this->controller_content->model,
            $profile,
            $folder_id
        ));

        if ($folders){
            $folders = array('0' => array('id' => '0', 'title' => LANG_ALL)) + $folders;
        }

        if ($this->cms_user->id != $profile['id'] && !$this->cms_user->is_admin){
            $this->controller_content->model->filterHiddenParents();
        }

        if ($this->cms_user->id == $profile['id'] || $this->cms_user->is_admin){
            $this->controller_content->model->disableApprovedFilter();
			$this->controller_content->model->disablePubFilter();
			$this->controller_content->model->disablePrivacyFilter();
        }

        // указываем тут сортировку, чтобы тут же указать индекс для использования
        $this->controller_content->model->orderBy('date_pub', 'desc')->forceIndex('user_id');

        list($ctype, $profile) = cmsEventsManager::hook('content_before_profile', array($ctype, $profile));

        if ($folder_id){
            $page_url = href_to_profile($profile, array('content', $ctype_name, $folder_id));
        } else {
            $page_url = href_to_profile($profile, array('content', $ctype_name));
        }

        $list_html = $this->controller_content->renderItemsList($ctype, $page_url.($dataset ? '/'.$dataset : ''));

        $list_header = empty($ctype['labels']['profile']) ? $ctype['title'] : $ctype['labels']['profile'];

        if(isset($current_dataset) && $dataset){
            $list_header .= ' / '.$current_dataset['title'];
        }

        return $this->cms_template->render('profile_content', array(
            'user'            => $this->cms_user,
            'id'              => $profile['id'],
            'profile'         => $profile,
            'ctype'           => $ctype,
            'folders'         => $folders,
            'folder_id'       => $folder_id,
            'datasets'        => $datasets,
            'dataset'         => $dataset,
            'current_dataset' => (isset($current_dataset) ? $current_dataset : array()),
            'base_ds_url'     => $page_url . '%s',
            'list_header'     => $list_header,
            'html'            => $list_html
        ));

    }

}
