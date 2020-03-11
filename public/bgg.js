$(function() {
    $('input').focus();
    $('select').change(function() {
        $('#form').submit();
    });
    $('button').click(function() {
        $('select').val('');
        $('input').val('');
        $('#form').submit();
    });
    $('#table').DataTable({
        order: [[2, 'desc']],
        paging: false,
        searching: false,
    });
    $('table tbody tr').click(function() {
        window.open('http://boardgamegeek.com/boardgame/' + $(this).data('game-id'));
    });
});
