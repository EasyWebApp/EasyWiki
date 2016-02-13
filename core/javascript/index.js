(function (BOM, DOM, $) {

    function Data_Fix(iArray) {
        return  $.map(iArray,  function (iValue) {
            if (iValue.time)
                iValue.time = (new Date(iValue.time)).toLocaleString();

            if ( (iValue[Image_Key] || '').indexOf('http') )
                iValue[Image_Key] = Image_Root + iValue[Image_Key];

            return iValue;
        });
    }

    $('#Main_View').on('pageRender',  function (iEvent, This_Page, Prev_Page, iData) {
        var _TP_ = $.fileName(This_Page.HTML),
            _PP_ = $.fileName(Prev_Page.HTML);

        if ((! $.isEmptyObject(iData))  &&  (iData.status === false))
            return BOM.alert(iData.msg);

        if (_TP_.slice(-3) != '.md')  return;

        $('a[href]', this).attr('href',  function () {
            return  (
                ($.urlDomain( arguments[1] )  ?  ''  :  '#!')  +  arguments[1]
            );
        });
    }).WebApp();

})(self, self.document, self.iQuery);