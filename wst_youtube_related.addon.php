<?php
// Copyright 2020 Webstack. All rights reserved.
// 본 소프트웨어는 웹스택이 개발/운용하는 웹스택의 재산으로, 허가없이 무단 이용할 수 없습니다.
// 무단 복제 또는 배포 등의 행위는 관련 법규에 의하여 금지되어 있으며, 위반시 민/형사상의 처벌을 받을 수 있습니다.
// 관련 문의는 웹사이트<https://webstack.me/> 또는 이메일<admin@webstack.me> 로 부탁드립니다.

if(!defined('__XE__'))
{
    exit();
}

// 라이프 사이클 확인
if($called_position != 'after_module_proc')
{
    return;
}

// 애드온 함수 모음 불러오기
require_once(__DIR__ . '/functions.php');

// Act 확인
if(!AddonFunction::compareAct(''))
{
    return;
}

// 게시글 존재 여부 확인
$oDocument = AddonFunction::getDocument();
if($oDocument == null)
{
    return;
}

// 애드온 설정 기본값 설정
$addon_info = AddonFunction::setDefaultAddonInfo($addon_info, [
    'skin' => 'default',
    'position' => 'bottom',

    'title_filter' => '',
    'channel_filter' => ''
]);

// 유튜브 검색 API 함수
if(!function_exists('wst_youtube_api_uo_search'))
{
    function wst_youtube_api_uo_search($q)
    {
        $param = array(
            'search_query' => $q
        );

        // curl 으로 유튜브 검색 결과 크롤링
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.youtube.com/results?' . http_build_query($param));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);
        curl_close($ch);

        // 영상 ID 정보가 없을 경우 빈 값 배열
        if(strpos($output, '{"webCommandMetadata":{"url":"/watch?v=') === false)
        {
            return new stdClass();
        }

        $data = explode('{"webCommandMetadata":{"url":"/watch?v=', $output);

        // 결과객체 생성
        $ret = new stdClass();
        $ret->list_count = count($data) - 2;
        $ret->query = $q;
        $ret->data = [];

        // 각 영상 정보 확인
        for($i = 1; $i < count($data); $i++)
        {
            // 데이터 추출
            $item = $data[$i];
            $item_p = $data[$i - 1];

            // 영상 기본정보 확인
            $it = new stdClass();
            $it->video_id = explode('"', $item)[0];
            $it->url = 'https://youtube.com/watch?v=' . $it->video_id;
            $it->thumbnail = 'https://i.ytimg.com/vi/' . $it->video_id . '/mqdefault.jpg';

            $it->title = explode('"}],', explode('"title":{"runs":[{"text":"', $item_p)[1])[0];
            $it->channel = explode('","navigationEndpoint"', explode('"ownerText":{"runs":[{"text":"', $item)[1])[0];

            $ret->data[] = $it;
        }

        return $ret;
    }
}

// 검색 쿼리 읽어오기
$title = html_entity_decode($oDocument->get('title'), ENT_QUOTES);
$tag_list = $oDocument->get('tag_list');
if(count($tag_list) > 0)
{
    $title = implode(' ', $tag_list);
}

$title = implode(' ', array_slice(explode(' ', $title), 0, 3));

// 캐시 조회
$cache_data = AddonFunction::getCache(md5($title));
if($cache_data !== null)
{
    $api_result = $cache_data;
    if($api_result->list_count == 0)
    {
        return;
    }
}

// 채널명 정보가 로드되지 않았을 경우 (20.06.03 업데이트 지원)
if(!isset($api_result->data[0]->channel))
{
    $api_result = null;
}

// API 호출
if(!$api_result)
{
    $api_result = wst_youtube_api_uo_search($title);
    if(!isset($api_result->query))
    {
        return;
    }

    // 캐시 설정
    AddonFunction::setCache(md5($title), $api_result, 60 * 60 * 24 * 7);

    if($api_result->list_count == 0)
    {
        return;
    }
}

// 유튜브 제목 필터링
if($addon_info->title_filter != '')
{
    $filter_list = explode("\n", str_replace("\r", '', $addon_info->title_filter));
    foreach($api_result->data as $i => $data)
    {
        foreach($filter_list as $filter)
        {
            if(strpos($data->title, $filter) !== false)
            {
                unset($api_result->data[$i]);
                break;
            }
        }

        if(count($api_result->data) == 0)
        {
            return;
        }
    }
}

// 유튜브 채널명 필터링
if($addon_info->channel_filter != '')
{
    $filter_list = explode("\n", str_replace("\r", '', $addon_info->channel_filter));
    foreach($api_result->data as $i => $data)
    {
        if(!isset($data->channel))
        {
            continue;
        }
        
        foreach($filter_list as $filter)
        {
            if(strpos($data->channel, $filter) !== false)
            {
                unset($api_result->data[$i]);
                break;
            }
        }

        if(count($api_result->data) == 0)
        {
            return;
        }
    }
}

$api_result->data = array_values($api_result->data);

Context::set('addon_info', $addon_info);
Context::set('youtube_list', $api_result->data);

// 템플릿 빌딩
if(!file_exists(__DIR__ . '/skins/' . $addon_info->skin . '/youtube.html'))
{
    $addon_info->skin = 'default';
}

$oTemplate = TemplateHandler::getInstance();
$template_compiled = $oTemplate->compile(__DIR__ . '/skins/' . $addon_info->skin, 'youtube');

switch($addon_info->position)
{
    case 'bottom':
        $oDocument->variables['content'] .= $template_compiled;
        break;

    case 'top':
        $oDocument->variables['content'] = $template_compiled . $oDocument->variables['content'];
        break;
        
    case 'custom':
        AddonFunction::setAddonSession('template_compiled', $template_compiled);
        if(!function_exists('printWstYoutubeRelated'))
        {
            function printWstYoutubeRelated()
            {
                echo AddonFunction::getAddonSession('template_compiled');
                return;
            }
        }
        break;
}

Context::set('oDocument', $oDocument);
