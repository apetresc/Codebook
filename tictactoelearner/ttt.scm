; Data definition
; A tictactoe board is uniquely defined by a nine-element vector
; where the nth element corresponds to the spot on the board in
; the diagram below.
; 0 = blank space
; 1 = X
; 2 = O
; 0 | 1 | 2
; ----------
; 3 | 4 | 5
; ----------
; 6 | 7 | 8

(define col-sep " | ")
(define row-sep "----------")

(define (display-boards boardlist)
  (cond
    ((empty? boardlist) void)
    (else (display-board (first boardlist))
          (display-boards (rest boardlist)))))

(define (display-board board)
  (define (draw-piece numcode)
    (cond
      ((= 0 numcode) ".")
      ((= 1 numcode) "X")
      ((= 2 numcode) "O")))
  (display (draw-piece (vector-ref board 0)))
  (display col-sep)
  (display (draw-piece (vector-ref board 1)))
  (display col-sep)
  (display (draw-piece (vector-ref board 2)))
  (newline)
  (display row-sep)
  (newline)
  (display (draw-piece (vector-ref board 3)))
  (display col-sep)
  (display (draw-piece (vector-ref board 4)))
  (display col-sep)
  (display (draw-piece (vector-ref board 5)))
  (newline)
  (display row-sep)
  (newline)
  (display (draw-piece (vector-ref board 6)))
  (display col-sep)
  (display (draw-piece (vector-ref board 7)))
  (display col-sep)
  (display (draw-piece (vector-ref board 8)))
  (newline)
  (newline)
  (display row-sep)
  (newline)
  (newline))

(define (transform-board board direction)
  (cond
    ((equal? direction 'H) 
     (vector (vector-ref board 6)
             (vector-ref board 7)
             (vector-ref board 8)
             (vector-ref board 3)
             (vector-ref board 4)
             (vector-ref board 5)
             (vector-ref board 0)
             (vector-ref board 1)
             (vector-ref board 2)))
    ((equal? direction 'V)
     (vector (vector-ref board 2)
             (vector-ref board 1)
             (vector-ref board 0)
             (vector-ref board 5)
             (vector-ref board 4)
             (vector-ref board 3)
             (vector-ref board 8)
             (vector-ref board 7)
             (vector-ref board 6)))
    ((equal? direction 'DR)
     (vector (vector-ref board 8)
             (vector-ref board 5)
             (vector-ref board 2)
             (vector-ref board 7)
             (vector-ref board 4)
             (vector-ref board 1)
             (vector-ref board 6)
             (vector-ref board 3)
             (vector-ref board 0)))
    ((equal? direction 'DL)
     (vector (vector-ref board 0)
             (vector-ref board 3)
             (vector-ref board 6)
             (vector-ref board 1)
             (vector-ref board 4)
             (vector-ref board 7)
             (vector-ref board 2)
             (vector-ref board 5)
             (vector-ref board 8)))))

(define (board-equal? board1 board2)
  (and (= (vector-ref board1 0) (vector-ref board2 0))
       (= (vector-ref board1 1) (vector-ref board2 1))
       (= (vector-ref board1 2) (vector-ref board2 2))
       (= (vector-ref board1 3) (vector-ref board2 3))
       (= (vector-ref board1 4) (vector-ref board2 4))
       (= (vector-ref board1 5) (vector-ref board2 5))
       (= (vector-ref board1 6) (vector-ref board2 6))
       (= (vector-ref board1 7) (vector-ref board2 7))
       (= (vector-ref board1 8) (vector-ref board2 8))))

(define (remove-duplicates lst)
  (define (board-in-list? board lst)
    (cond ((empty? lst) #f)
          (else (or (board-equal? board (first lst))
                    (board-in-list? board (rest lst))))))
  (if (empty? lst) lst
      (let ((a (first lst)) (d (rest lst)))
        (if (board-in-list? a d) (remove-duplicates d)
            (cons a (remove-duplicates d))))))

(define (generate-all-symmetries board)
  (remove-duplicates 
   (list board
         (transform-board board 'H)
         (transform-board board 'V)
         (transform-board board 'DR)
         (transform-board board 'DL)
         (transform-board (transform-board board 'DL) 'DR)
         (transform-board (transform-board board 'V) 'DL)
         (transform-board (transform-board board 'H) 'DL))))

(define (play-all-moves board player)
  (define (numcode player)
    (cond ((equal? player 'X) 1)
          ((equal? player 'O) 2)))
  (define (play-move board spot player)
    (define (play-move-helper board spot player)
      (define newboard (vector-map identity board))
      (vector-set! newboard spot (numcode player))
      newboard)
    (cond ((= (vector-ref board spot) 0)
           (play-move-helper board spot player))))
  (map (lambda (x) (play-move board x player))
       '(0 1 2 3 4 5 6 7 8)))


(define board (make-vector 9 0))
;(display-board board)
;(vector-set! board 0 2)
;(vector-set! board 5 1)
(vector-set! board 4 1)
;(vector-set! board 8 1)
(define symlist (generate-all-symmetries board))
(display-boards symlist)
(length symlist)
(display-boards (play-all-moves board 'O))
;(display-board board)
;(define v-board (transform-board board 'V))
;(display-board v-board)