<html>
    <head>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    </head>

    <body>

    <h1>Hello, {{ $name }}</h1>
    <form>
        To: <input type="text" size="50" name="to_name" id="to_name" value="{{ $to }}" /><br />
        Message: <input type="text" size="50" name="textbox" id="textbox"/></form>
    <div style="padding:10px;margin:10px;" id="chatbox">
    </div>

    <script>
        function log( msg ) {
            $('#chatbox').append(msg);
        }

        $(document).ready(function(){

            $('#textbox').keypress(function(e) {
                if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
                    $.ajax({
                        type: "GET",
                        url: "/talk",
                        data: "name={{ $name }}&to="
                        + encodeURIComponent($('#to_name').val())
                        + "&message="
                        + encodeURIComponent( $('#textbox').val() ),
                        success: function(msg) {
                            log("<br /><b>{{ $name }}</b>&nbsp;"
                                + $('#textbox').val() + " (sent) <br />");
                            $('#textbox').val("");
                        }
                    });
                }
            });

            chatReceiver = function(data) {
                if ( data != null ) {
                    log( "<br /><b>" + data.from + "</b>&nbsp;" + data.message + "(received) <br />");
                }
                $.ajax({
                    type: 'GET',
                    url: '/listen',
                    data: 'name={{ $name }}',
                    success: chatReceiver,
                    error: function(req, status, error) {
                        if ( error.status == 408 )
                            chatReceiver(null);
                        else setTimeout(chatReceiver(null), 5000);
                    }
                });
            };

            chatReceiver(null);

        });

    </script>
    </body>
</html>