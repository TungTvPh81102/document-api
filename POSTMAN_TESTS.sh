#!/bin/bash

###############################################################################
# POSTMAN TEST CASES - Polymorphic Media Upload System
# 
# 5 test cases:
#   1. Upload avatar cho User
#   2. Upload ảnh vào gallery của Post
#   3. Upload document cho Product
#   4. Xóa 1 media
#   5. Lấy danh sách media của Post
#
# Chuẩn bị trước khi chạy:
#   1. Thay đổi BASE_URL nếu cần
#   2. Thay đổi TOKEN (Sanctum token từ /api/auth/login)
#   3. Thay đổi USER_ID, POST_ID, PRODUCT_ID theo dữ liệu thực tế
#   4. Thay đổi đường dẫn file ảnh/PDF
#
###############################################################################

# ===== CONFIG =====
BASE_URL="http://localhost:8000"
API_PREFIX="/api/v1/media"

# Sanctum Token (lấy từ login)
TOKEN="your_sanctum_token_here"

# Model IDs (thay bằng ID thực tế)
USER_ID=1
POST_ID=1
PRODUCT_ID=1
MEDIA_ID=1  # (sau khi upload thành công)

# Đường dẫn file test
AVATAR_FILE="${HOME}/Downloads/avatar.jpg"
GALLERY_FILE="${HOME}/Downloads/photo.jpg"
DOCUMENT_FILE="${HOME}/Downloads/spec.pdf"

echo "════════════════════════════════════════════════════════════════"
echo "POLYMORPHIC MEDIA UPLOAD - TEST CASES"
echo "════════════════════════════════════════════════════════════════"

###############################################################################
# TEST 1: Upload avatar cho User
###############################################################################

echo -e "\n[TEST 1] Upload avatar cho User"
echo "────────────────────────────────────────────────────────────────"

curl -X POST "${BASE_URL}${API_PREFIX}/user/${USER_ID}/avatar" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  -F "file=@${AVATAR_FILE}" \
  | jq .

echo -e "\n✓ Response: Check 'success' = true, 'url' có Cloudinary path: user/{id}/avatar/"

###############################################################################
# TEST 2: Upload ảnh vào gallery của Post
###############################################################################

echo -e "\n[TEST 2] Upload ảnh vào gallery của Post"
echo "────────────────────────────────────────────────────────────────"

curl -X POST "${BASE_URL}${API_PREFIX}/post/${POST_ID}/gallery" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  -F "file=@${GALLERY_FILE}" \
  | jq .

echo -e "\n✓ Response: Check 'success' = true, lưu ý:'url' path: posts/{id}/gallery/"

###############################################################################
# TEST 3: Upload document cho Product
###############################################################################

echo -e "\n[TEST 3] Upload document cho Product"
echo "────────────────────────────────────────────────────────────────"

curl -X POST "${BASE_URL}${API_PREFIX}/product/${PRODUCT_ID}/documents" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  -F "file=@${DOCUMENT_FILE}" \
  | jq .

echo -e "\n✓ Response: Check 'success' = true, 'url' path: products/{id}/documents/"
echo "⚠ Lưu ý MEDIA_ID từ response để dùng ở TEST 4"

###############################################################################
# TEST 4: Xóa 1 media
###############################################################################

echo -e "\n[TEST 4] Xóa 1 media"
echo "────────────────────────────────────────────────────────────────"

curl -X DELETE "${BASE_URL}${API_PREFIX}/${MEDIA_ID}" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  | jq .

echo -e "\n✓ Response: Check 'success' = true, 'message' = 'Xóa thành công'"

###############################################################################
# TEST 5: Lấy danh sách media của Post
###############################################################################

echo -e "\n[TEST 5] Lấy danh sách media của Post"
echo "────────────────────────────────────────────────────────────────"

curl -X GET "${BASE_URL}${API_PREFIX}/post/${POST_ID}" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  | jq .

echo -e "\n✓ Response structure:"
echo "  {
    \"success\": true,
    \"data\": {
      \"thumbnail\": null,                            ← null nếu chưa upload
      \"gallery\": [ {...}, {...} ],                ← array của media objects
      \"documents\": []                             ← array (có thể rỗng)
    }
  }"

###############################################################################
# ADDITIONAL TEST CASES
###############################################################################

echo -e "\n════════════════════════════════════════════════════════════════"
echo "ADDITIONAL TEST CASES"
echo "════════════════════════════════════════════════════════════════"

###############################################################################
# TEST 6: Upload file lỗi (file size quá lớn)
###############################################################################

echo -e "\n[TEST 6] Upload file lỗi - File size quá lớn (422)"
echo "────────────────────────────────────────────────────────────────"

curl -X POST "${BASE_URL}${API_PREFIX}/user/${USER_ID}/avatar" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  -F "file=@/very/large/file.jpg" \
  | jq .

echo -e "\n✓ Response: 'success' = false, 'message' = 'Kích thước file vượt quá giới hạn'"

###############################################################################
# TEST 7: Upload file lỗi (định dạng không hỗ trợ)
###############################################################################

echo -e "\n[TEST 7] Upload file lỗi - Định dạng không hỗ trợ (422)"
echo "────────────────────────────────────────────────────────────────"

curl -X POST "${BASE_URL}${API_PREFIX}/product/${PRODUCT_ID}/documents" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  -F "file=@image.jpg" \
  | jq .

echo -e "\n✓ Response: 'success' = false, 'message' = 'Định dạng file không được hỗ trợ'"

###############################################################################
# TEST 8: Không có quyền (403) - Upload vào model của user khác
###############################################################################

echo -e "\n[TEST 8] Không có quyền - Upload vào Post của user khác (403)"
echo "────────────────────────────────────────────────────────────────"

curl -X POST "${BASE_URL}${API_PREFIX}/post/999/gallery" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  -F "file=@${GALLERY_FILE}" \
  | jq .

echo -e "\n✓ Response: 'success' = false, 'message' = 'Bạn không có quyền thực hiện hành động này'"

###############################################################################
# TEST 9: Model/Collection không tồn tại (422)
###############################################################################

echo -e "\n[TEST 9] Model/Collection không tồn tại (422)"
echo "────────────────────────────────────────────────────────────────"

curl -X POST "${BASE_URL}${API_PREFIX}/non_existent_model/1/avatar" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  -F "file=@${AVATAR_FILE}" \
  | jq .

echo -e "\n✓ Response: 'success' = false, HTTP error 422"

###############################################################################
# TEST 10: Không có token (401)
###############################################################################

echo -e "\n[TEST 10] Không có token (401)"
echo "────────────────────────────────────────────────────────────────"

curl -X GET "${BASE_URL}${API_PREFIX}/user/1" \
  -H "Accept: application/json" \
  | jq .

echo -e "\n✓ Response: Unauthenticated error, HTTP 401"

###############################################################################
# USEFUL QUERIES
###############################################################################

echo -e "\n════════════════════════════════════════════════════════════════"
echo "USEFUL QUERIES"
echo "════════════════════════════════════════════════════════════════"

echo -e "\n[DEBUG] Lấy toàn bộ media của User với pretty output:"
echo "────────────────────────────────────────────────────────────────"
echo "curl -X GET '${BASE_URL}${API_PREFIX}/user/${USER_ID}' \\"
echo "  -H 'Authorization: Bearer \${TOKEN}' \\"
echo "  -H 'Accept: application/json' | jq ."

echo -e "\n[DEBUG] Upload avatar (auto-replace file cũ):"
echo "────────────────────────────────────────────────────────────────"
echo "curl -X POST '${BASE_URL}${API_PREFIX}/user/${USER_ID}/avatar' \\"
echo "  -H 'Authorization: Bearer \${TOKEN}' \\"
echo "  -F 'file=@new_avatar.jpg' | jq ."

echo -e "\n[DEBUG] Xóa media khác:"
echo "────────────────────────────────────────────────────────────────"
echo "curl -X DELETE '${BASE_URL}${API_PREFIX}/\${MEDIA_ID}' \\"
echo "  -H 'Authorization: Bearer \${TOKEN}' | jq ."

echo -e "\n════════════════════════════════════════════════════════════════"
echo "✓ All tests completed!"
echo "════════════════════════════════════════════════════════════════"
