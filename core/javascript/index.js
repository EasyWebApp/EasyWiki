//
//                >>>  EasyWiki  <<<
//
//
//      [Version]    v0.5  (2016-03-02)  Beta
//
//      [Require]    iQuery  ||  jQuery with jQuery+,
//
//                   iQuery+  v0.7+,
//
//                   marked.js  v0.3+,
//
//                   EasyWebApp  v2.3+,
//
//                   jQuery-QRcode  v0.12+
//
//
//            (C)2016    shiy2008@gmail.com
//


(function (BOM, DOM, $) {

    $('body > *').css('max-height',  $(BOM).height() - 220);

    function Data_Fix(iArray) {
        return  $.map(iArray,  function (iValue) {
            if (iValue.time)
                iValue.time = (new Date(iValue.time)).toLocaleString();

            if ( (iValue[Image_Key] || '').indexOf('http') )
                iValue[Image_Key] = Image_Root + iValue[Image_Key];

            return iValue;
        });
    }

    var $_MainView = $('#Main_View');

    var iMainNav = $.TreeView(
            $.ListView('#Main_Nav',  function ($_Item, iValue) {

                $('a[rel="nofollow"]', $_Item[0]).text(iValue.text)[0].href =
                    '#' + iValue.id;
                $_Item.attr('title', iValue.text);
            }),
            'list',
            function () {
                this.$_View.attr('class', '');
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

    $_MainView.on('pageRender',  function (iEvent, This_Page, Prev_Page, iData) {
        var _TP_ = $.fileName(This_Page.HTML),
            _PP_ = $.fileName(Prev_Page.HTML);

        if ((! $.isEmptyObject(iData))  &&  (iData.status === false))
            return BOM.alert(iData.msg);

        if (_TP_.slice(-3) != '.md') {
            $_Body.removeClass('Entry_Content');
            return;
        }

        $_Body.addClass('Entry_Content');

        $('#QRcode > .Body').empty().qrcode({
            render:     $.browser.modern ? 'image' : 'div',
            ecLevel:    'H',
            radius:     0.5,
            mode:       2,
            label:      $('h1', this).text().slice(0, 10),
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