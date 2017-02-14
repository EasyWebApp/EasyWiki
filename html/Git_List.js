define(['jquery'],  function ($) {

    return  function (iData) {

        $.ajaxPrefilter(function (iOption, _, iXHR) {

            if (iOption.url.indexOf( iData.Git_API )  >  -1)
                iXHR.setRequestHeader(
                    'Authorization',  'token ' + iData.Git_Token
                );
        });
    };
});