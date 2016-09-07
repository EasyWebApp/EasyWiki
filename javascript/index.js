define(['jquery', 'marked', 'EasyWebUI', 'EasyWebApp'],  function ($, marked) {

    $.ajaxSetup({
        dataFilter:    function (iText) {
            return  ($.fileName( this.url ).match(/\.(md|markdown)$/i))  ?
                marked( iText )  :  iText;
        }
    });

    $(document).ready(function () {

        $('body > .Head > .NavBar').scrollFixed(function () {
            $(this.firstElementChild)[
                (arguments[0] == 'fixed')  ?  'addClass'  :  'removeClass'
            ]('focus');
        });

        $('body > .PC_Narrow').iWebApp()
            .on('data',  '',  'index.json',  function (iLink, iData) {

                $.ListView(iLink.$_DOM,  false,  function ($_Item, iValue) {

                    $_Item.children().attr(iValue);
                });

                return iData;
            });
    });
});
