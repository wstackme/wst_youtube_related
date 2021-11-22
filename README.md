# wst_youtube_related

게시글과 연관된 유튜브의 목록을 출력해 주는 애드온

## 설치방법

./addons/wst_youtube_related 폴더에 파일을 추가합니다.

Git을 활용할 경우 좀 더 편리하게 설치하고, 업데이트 할 수 있습니다.
```
cd ./addons/
git clone https://github.com/wstackme/wst_youtube_related

# 업데이트 시
git pull origin
```

## 설정법

### 사용자 지정 출력 위치

애드온 설정에서 출력 설정 > 출력 위치 > 사용자 지정 옵션을 통해 원하는 위치에 목록을 출력할 수 있습니다.

이 옵션을 선택할 경우, `printWstYoutubeRelated()` 함수를 통해 목록을 출력할 수 있습니다.

연관유튜브 목록이 존재하지 않는 경우를 대비하여, 아래 방식을 사용하여 스킨에 등록하는 것이 안전합니다.

```
<block cond="function_exists('printWstYoutubeRelated')">
    {@ printWstYoutubeRelated(); }
</block>
```

또는 아래와 같은 방법을 사용할 수도 있습니다.

```
{@
    if(function_exists('printWstYoutubeRelated')):
        printWstYoutubeRelated();
    endif;
}
```