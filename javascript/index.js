define([
    'jquery', 'marked', 'MediumEditor', 'EasyWebUI', 'EasyWebApp', 'QRcode'
],  function ($, marked, MediumEditor) {

    $.ajaxSetup({
        dataFilter:    function (iText) {
            return  ($.fileName( this.url ).match(/\.(md|markdown)$/i))  ?
                marked( iText )  :  iText;
        }
    });

    $(document).ready(function () {

        $('#Main_Nav').scrollFixed(function () {
            $(this.firstElementChild)[
                (arguments[0] == 'fixed')  ?  'addClass'  :  'removeClass'
            ]('focus');
        });

        var $_App = $('#Main_Content');

        $('#Toolkit .Icon.Pen').click(function () {

            if ($_App[0].contentEditable == "true")
                MediumEditor.getEditorFromElement( $_App[0] ).destroy();
            else
                new MediumEditor( $_App[0] );
        });

        var $_ReadNav = $('#Content_Nav').iReadNav( $_App ).scrollFixed();

        $_App.iWebApp().on('data',  '',  'index.json',  function () {

            $.ajaxSetup({
                headers:    {
                    Authorization:    'token ' + arguments[1].Git_Token
                }
            });
        }).on('ready',  '\\.(html|md)',  function () {

            $_ReadNav.trigger('Refresh');

            var iTitle = this.$_Root.find('h1').text() || '';

            document.title = iTitle + ' - EasyWiki';

            $('#QRcode > .Body').empty().qrcode({
                render:     $.browser.modern ? 'image' : 'div',
                ecLevel:    'H',
                radius:     0.5,
                mode:       2,
                label:      iTitle.slice(0, 10),
                text:       self.location.href
            });

        }).on('data',  '',  '/contents/',  function (_, iData) {
            return {
                content:    $.map(iData,  function () {
                    return  (arguments[0].type != 'dir')  ?  null  :  arguments[0];
                })
            };
        });
    });
});