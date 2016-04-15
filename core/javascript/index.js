//
//                >>>  EasyWiki  <<<
//
//
//      [Version]    v0.9  (2016-03-31)  Beta
//
//      [Require]    iQuery  ||  jQuery with jQuery+,
//
//                   iQuery+  v0.7+,
//
//                   marked.js  v0.3+,
//
//                   EasyWebApp  v2.3+,
//
//                   jQuery-QRcode  v0.12+,
//
//                   Editor.md  v1.5+
//
//
//            (C)2016    shiy2008@gmail.com
//


(function (BOM, DOM, $) {

    $('body > *').css('max-height',  $(BOM).height() - 220);

    var $_MainView = $('#Main_View');

    var iMainNav = $.TreeView(
            $.ListView('#Main_Nav',  function ($_Item, iValue) {

                $('a[rel="nofollow"]', $_Item[0]).text(iValue.text)[0].href =
                    '#' + iValue.id;
                $_Item.attr('title', iValue.text);
            }),
            'list',
            function () {
                arguments[0].$_View.attr('class', '');
            },
            function () {
                var $_Target = $(arguments[0].target);

                if ( $_Target.is('a[rel="nofollow"]') )
                    return (
                        '*[id="'  +  $_Target.attr('href').slice(1)  +  '"]'
                    );
            }
        ).linkage($_MainView,  function ($_Anchor) {
            $_Anchor = $_Anchor.prevAll('h1, h2, h3');

            if (! $.contains(this, $_Anchor[0]))  return;

            $_Anchor = $('#Main_Nav a[href="#' + $_Anchor[0].id + '"]');

            $('#Main_Nav li.active').removeClass('active');

            $.ListView.getInstance( $_Anchor.parents('ul')[0] )
                .focus( $_Anchor[0].parentNode );
        });

    var $_Body = $(DOM.body).swipe(function () {
            iMainNav.unit.$_View[
                (arguments[0].deltaX < 0)  ?  'show'  :  'hide'
            ]();
        });

    function Time_Fix(UTS) {
        return  (new Date(UTS * 1000)).toLocaleString();
    }

    function DataFix(iData, iFixer) {
        for (var i = 0;  i < iData.length;  i++)
            for (var iKey in iData[i])  if (iFixer[iKey])
                iData[i][iKey] = iFixer[iKey].call(iData[i], iData[i][iKey]);

        return iData;
    }

    function Load_Editor(MD_URL) {
        var iCDN = 'https://pandao.github.io/editor.md/',
            iReady = MD_URL ? 2 : 1,
            iMarkDown;

        function Editor_Init() {
            if (MD_URL  &&  (typeof arguments[0] == 'string'))
                iMarkDown = arguments[0];
            if (--iReady > 0)  return;

            editormd('Editor_MD', {
                path:                 iCDN + 'lib/',
//                autoLoadModules:      false,
                height:               $('#Editor_MD').css('min-height'),
//                autoHeight:           true,
                theme:                'dark',
                editorTheme:          'pastel-on-dark',
                previewTheme:         'dark',
                taskList:             true,
                htmlDecode:
                    'style,script,frameset,iframe,object,embed|on*',
                markdown:             iMarkDown  ||  "# （词条名不可少）",
                emoji:                true,
                imageUpload:          true,
                imageFormats:         [
                    'jpg', 'jpeg', 'gif', 'png', 'bmp', 'webp'
                ],
                imageUploadURL:       'core/api.php/image/',
                syncScrolling:        true,
                dialogMaskOpacity:    0.5
            });
        }

        if (MD_URL)  $.get(MD_URL, Editor_Init);

        if (BOM.iQuery !== BOM.jQuery)  return Editor_Init();

        ImportJS('http://cdn.bootcss.com/', [
            {
                'jquery/2.2.1/jquery.min.js':     $.browser.modern,
                'jquery/1.12.1/jquery.min.js':    (! $.browser.modern)
            },/*
            [
                'codemirror/5.12.0/codemirror.min.js',
                iCDN + 'lib/codemirror/modes.min.js',
                iCDN + 'lib/codemirror/addons.min.js',
                'prettify/r298/prettify.min.js'
            ],*/
            iCDN + 'editormd.min.js'
        ], Editor_Init);
    }

/* ---------- 权限控制 ---------- */

    function Auth_Control(iAuth) {
        var $_Auth = $('.Auth_Control[data-api]');

        for (var i = 0, _Auth_;  i < $_Auth.length;  i++) {
            _Auth_ = iAuth[ $_Auth[i].dataset.api ];

            $($_Auth[i])[
                (_Auth_ && _Auth_[$_Auth[i].dataset.method])  ?
                    'show' : 'hide'
            ]();
        }
    }
/*
    $.post('core/api.php/online/',  null,  function () {
        Auth_Control( arguments[0].auth );
    });
*/

    $_MainView.on('apiCall',  function () {
        var iResponse = arguments[2];

        if (iResponse.URL.indexOf('core/api.php/online/') < 0)  return;

        switch (iResponse.method.toUpperCase()) {
            case 'DELETE':    return BOM.location.replace('.');
            case 'POST':      Auth_Control( iResponse.data.auth );
        }
    }).on('pageRender',  function (iEvent, This_Page, Prev_Page, iData) {
        var _TP_ = $.fileName(This_Page.HTML),
            _PP_ = $.fileName(Prev_Page.HTML);

        if ((! $.isEmptyObject(iData))  &&  (iData.status === false))
            return BOM.alert(iData.msg);

        switch (_TP_) {
            case '_Search':
                DataFix(iData, {
                    cTime:    Time_Fix,
                    mTime:    Time_Fix,
                    URL:      function () {
                        return  BOM.location.href.split('#')[0] +
                            '#!' + arguments[0];
                    }
                });
                break;
            case 'signUp.html':    $('form', this).pwConfirm();    break;
            case 'spider.html':    {
                var $_Auto_Fetch = $('#Auto_Fetch');

                $('tbody tr', this).click(function () {
                    var $_Spider = $_MainView.find('form');

                    $_Spider.find('input[name="url"]')[0].value =
                        $('*[name="URL"]', this).value();
                    $_MainView.scrollTo($_Spider);

                    if ( $_Auto_Fetch[0].checked )
                        $_Spider.find('input[type="submit"]')[0].click();
                });
            }
            case '_Auth':
                DataFix(iData, {
                    cTime:    Time_Fix,
                    mTime:    Time_Fix
                });
                break;
            case 'auth.html':
                $('form', this).submit(function () {
                    if (! arguments[0].isTrusted)  return;

                    $('tbody tr', this).each(function () {
                        var $_JSON = $('input[type="hidden"]', this);

                        $_JSON.val(JSON.stringify(
                            $.paramJSON('?' + $(this).serialize())
                        )).attr('name', $_JSON.prevAll('label').text());
                    });
                });
        }
        if (_TP_.slice(-3) != '.md') {
            $_Body.addClass('Not_Entry');
            return iData;
        }

        $_Body.removeClass('Not_Entry');

        var iTitle = $('h1', this).text() || '';

        DOM.title = iTitle + ' - EasyWiki';

        $('#QRcode > .Body').empty().qrcode({
            render:     $.browser.modern ? 'image' : 'div',
            ecLevel:    'H',
            radius:     0.5,
            mode:       2,
            label:      iTitle.slice(0, 10),
            text:       BOM.location.href.split('#')[0] + '#!' + This_Page.HTML
        });

        $('a[href]', this).attr('href',  function () {
            if (! $.urlDomain(arguments[1])) {
                this.setAttribute('rel', 'nofollow');
                return  '#!data/' + arguments[1];
            }
            return arguments[1];
        });
    }).on('pageReady',  function () {

        var _TP_ = $.fileName( arguments[2].HTML ),
            _PP_ = $.fileName( arguments[3].HTML ),
            TP_Param = $.paramJSON( arguments[2].HTML );

        switch (_TP_) {
            case 'editor.html':    if (_TP_ != _PP_) {
                if (TP_Param.modify) {
                    Load_Editor( arguments[3].HTML );

                    $('form input[name="title"]', this).attr({
                        readonly:    true,
                        title:       "已存在的词条不能改名"
                    })[0].value = _PP_.split('.')[0];
                } else
                    Load_Editor();

                $('form input[name="type"]', this)[0].value =
                    TP_Param.category ? 1 : 0;
            }
        }
        if ( $_Body.hasClass('Not_Entry') )
            return iMainNav.unit.clear();

        iMainNav.bind(
            $('h1, h2, h3', this),
            function ($_A, $_B) {
                return  $_B.tagName[1] - $_A.tagName[1];
            },
            function () {
                if (this.id == '-')  this.id = $.uuid('Header');

                return {
                    id:      this.id,
                    text:    $(this).text()
                };
            }
        );
    }).WebApp();

})(self, self.document, self.iQuery);