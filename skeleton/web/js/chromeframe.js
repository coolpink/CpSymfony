/**
 * Ask to install Google Chrome Frame once every 7 days.
 */
$(function () {
  CFInstall.check({
    preventPrompt: true,
    oninstall: function() {
      window.location.reload(true);
    },
    onmissing: function () {
      var askChromeFrame = $.cookie('askedChromeFrame');
      if (!askChromeFrame) {
        $.cookie('askedChromeFrame', true, { expires: 7, path: '/' });
        var message = 'Internet Explorer is missing features that would make for a better experience, please install ' +
              '<a href="http://www.google.com/chromeframe" title="Install Google Chrome Frame" target="_blank">Google Chrome Frame</a>.';
        $(document.body).bar({
          message: message,
          type: 'jbar-info',
          position : 'bottom',
          removebutton: false,
          time: 10000
        });
      }
    }
  });
});