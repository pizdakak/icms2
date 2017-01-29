<?php

class actionContentItemTrashPut extends cmsAction {

    public function run(){

        // Получаем название типа контента и сам тип
        $ctype = $this->model->getContentTypeByName($this->request->get('ctype_name', ''));
        if (!$ctype) { cmsCore::error404(); }

        $id = $this->request->get('id', 0);
        if (!$id) { cmsCore::error404(); }

        $item = $this->model->getContentItem($ctype['name'], $id);
        if (!$item || !$item['is_approved']) { cmsCore::error404(); }

        // проверяем наличие доступа
        if (!cmsUser::isAllowed($ctype['name'], 'move_to_trash')) { cmsCore::error404(); }
        if (!cmsUser::isAllowed($ctype['name'], 'move_to_trash', 'all') && $item['user_id'] != $this->cms_user->id) { cmsCore::error404(); }

        $back_action = '';

        if ($ctype['is_cats'] && $item['category_id']){

            $category = $this->model->getCategory($ctype['name'], $item['category_id']);
            $back_action = $category['slug'];

        }

        $this->model->toTrashContentItem($ctype['name'], $item);

        $allow_delete = (cmsUser::isAllowed($ctype['name'], 'delete', 'all') ||
            (cmsUser::isAllowed($ctype['name'], 'delete', 'own') && $item['user_id'] == $this->cms_user->id));

        cmsUser::addSessionMessage(($allow_delete ? LANG_BASKET_DELETE_SUCCESS : LANG_DELETE_SUCCESS), 'success');

        $back_url = $this->request->get('back', '');

        if ($back_url){
            $this->redirect($back_url);
        } else {
            if ($ctype['options']['list_on']){
                $this->redirectTo($ctype['name'], $back_action);
            } else {
                $this->redirectToHome();
            }
        }

    }

}