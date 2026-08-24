<?php
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

$sql = "select * from CASH_CONFIG where IDX = {$idx} ";
$data = $db->get_data($sql);

?>

<div class="modal-header">
    <h6 class="modal-title">상품 등록/수정</h6>
    <button aria-label="Close" class="btn-close" data-bs-dismiss="modal">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<div class="modal-body">
    <div class="card-body" id="order-view" style="padding: 0;">
        <div class="table-responsive">
            <form id="w_frm" method="post" >
                <input type="hidden" name="uidx" value="<?=$data['IDX']?>" />

                <table class="table border text-nowrap table-bordered">
                    <tbody>

                    <tr>
                        <td id="modal-subject">제목</td>
                        <td>
                            <input type="text" class="form-control" name="CASH_NAME" value="<?=$data['CASH_NAME']?>" class="custom-form-control" />
                        </td>
                    </tr>
                    <tr>
                        <td id="modal-subject">금액</td>
                        <td>
                            <input type="text" class="form-control" name="AMOUNT" value="<?=$data['AMOUNT']?>" class="custom-form-control" />
                        </td>
                    </tr>
                    <tr>
                        <td id="modal-subject">지급될캐시</td>
                        <td>
                            <input type="text" class="form-control" name="CASH" value="<?=$data['CASH']?>" class="custom-form-control" />
                        </td>
                    </tr>
                    <tr>
                        <td id="modal-subject">지급될포인트</td>
                        <td>
                            <input type="text" class="form-control" name="POINT" value="<?=$data['POINT']?>" class="custom-form-control" />
                        </td>
                    </tr>

                    </tbody>
                </table>
            </form>
        </div>
    </div>
</div>
<div class="modal-footer" style="justify-content: space-between;">
    <div>
        <button type="button" class="btn btn-success " onclick="go_cash_update()" >Save</button>
        <button type="button" class="btn btn-light cancel_btn" data-bs-dismiss="modal">Close</button>
    </div>
</div>
